<?php

require_once 'config.php';

class MySQLRevisioningTool
{
    /**
     * @var mysqli $mysql
     */
    protected $mysql;

    /**
     * Constructor.
     *
     * @param array  $argv  Arguments.
     */
    public function __construct($argv)
    {
        if (isset($argv[1]) && $argv[1] == './mrt.sh') {
            list($script, $sh, $method, $revision) = $argv + array(__FILE__, null, 'update', PHP_INT_MAX);
        }
        else {
            list($script, $method, $revision) = $argv + array(__FILE__, 'update', PHP_INT_MAX);
        }

        echo "--------------------------------------------------------------------------------\n";
        $this->{"run{$method}"}($revision);
        echo "\n";
    }

    /**
     * Updates database to the latest revision.
     *
     * @return bool
     */
    public function runUpdate()
    {
        $this->start();
        $this->fkCheck(false);

        $currentRevision = $this->revision();

        $deltas = $this->deltas();

        $updateRevision = $currentRevision;
        foreach ($deltas as $revision => $fileName) {
            if ($revision > $currentRevision) {
                $updateRevision = $revision;
                list($updateSql, $revertSql) = explode('--@UNDO', file_get_contents(PATH_TO_DELTAS . $fileName));

                echo COLOR_WHITE . ">>> Update {$revision}: {$fileName} \n";
                echo COLOR_GREEN . trim($updateSql) . "\n\n";

                $this->mysql()->multi_query(trim($updateSql));

                $this->cleanResults();

                if ($this->mysql()->errno) {
                    echo COLOR_RED . ">>> Error:\n" . $this->mysql()->error . ">>> Query:\n" . $updateSql;
                    $this->rollback();
                    return false;
                }
            }
        }

        if ($currentRevision == $updateRevision) {
            echo COLOR_GREEN . ">>> Database is at the latest revision.\n";
        }
        else {
            $this->updateRevision($updateRevision);
        }

        $this->fkCheck(true);
        $this->commit();

        if ($this->mysql()->errno) {
            echo COLOR_RED . ">>> Error:\n" . $this->mysql()->error . "\n";
            return false;
        }

        $this->mysql()->close();

        return true;
    }

    /**
     * Revert db to the specified revision.
     *
     * @param int  $revision  Version to revert to.
     *
     * @return bool
     */
    public function runRevision($revision)
    {
        $currentRevision = $this->revision();
        $updateRevision  = $currentRevision;

        $deltas = $this->deltas();

        /*
         * 2 possibilities
         * - Revision < Current => revert starting with current version
         * - Revision > Current => update to revision
         */

        if ($revision < $currentRevision) {
            // revert to revision starting with current revision
            krsort($deltas);

            foreach ($deltas as $deltaRevision => $fileName) {
                if ($deltaRevision <= $currentRevision && $deltaRevision > $revision) {
                    $updateRevision = $revision;
                    list($updateSql, $revertSql) = explode('--@UNDO', file_get_contents(PATH_TO_DELTAS . $fileName));

                    echo COLOR_WHITE . ">>> Revert {$deltaRevision}: {$fileName} \n";
                    echo COLOR_GREEN . trim($revertSql) . "\n\n";

                    $this->mysql()->multi_query(trim($revertSql));

                    $this->cleanResults();

                    if ($this->mysql()->errno) {
                        echo COLOR_RED . ">>> Error:\n" . $this->mysql()->error . ">>> Query:\n" . $revertSql;
                        $this->rollback();
                        return false;
                    }
                }
            }
        }
        elseif ($revision > $currentRevision) {
            foreach ($deltas as $deltaRevision => $fileName) {
                if ($deltaRevision > $currentRevision && $deltaRevision <= $revision) {
                    $updateRevision = $revision;
                    list($updateSql, $revertSql) = explode('--@UNDO', file_get_contents(PATH_TO_DELTAS . $fileName));

                    echo COLOR_WHITE . ">>> Update {$deltaRevision}: {$fileName} \n";
                    echo COLOR_GREEN . trim($updateSql) . "\n\n";

                    $this->mysql()->multi_query(trim($updateSql));

                    $this->cleanResults();

                    if ($this->mysql()->errno) {
                        echo COLOR_RED . ">>> Error:\n" . $this->mysql()->error . ">>> Query:\n" . $updateSql;
                        $this->rollback();
                        return false;
                    }
                }
            }
        }

        if ($currentRevision == $updateRevision) {
            echo COLOR_GREEN . ">>> No changes performed.\n";
        }
        else {
            $this->updateRevision($updateRevision);
        }

        $this->fkCheck(true);
        $this->commit();

        if ($this->mysql()->errno) {
            echo COLOR_RED . ">>> Error:\n" . $this->mysql()->error . "\n";
            return false;
        }

        $this->mysql()->close();

        return true;
    }

    /**
     * Show current database revision.
     *
     * @return null
     */
    public function runStatus()
    {
        echo COLOR_GREEN . ">>> Current database revision: " . $this->revision() . "\n";

        $this->mysql()->close();
    }

    /**
     * Show help message.
     *
     * @return null
     */
    public function runHelp()
    {
        echo str_replace(array('{white}', '{gray}'), array(COLOR_WHITE, COLOR_COMMENT), "
{white}Help usage:

{gray}# help - for this message
{white}php script.php help

{gray}# to install revision table
{white}php script.php install

{gray}# to update to the latest available revision
{white}php script.php update

{gray}# go to a specific revision number
{white}php script.php revision [number]

{gray}# show the current database revision
{white}php script.php status

");
    }

    /**
     * Lazy-loading of mysqli connection.
     *
     * @return mysqli
     */
    public function mysql()
    {
        if (!$this->mysql) {
            $this->mysql = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($this->mysql->connect_error) {
                echo COLOR_RED . ">>> Error: " . $this->mysql->connect_error . "\n\n";
                exit;
            }
        }

        return $this->mysql;
    }

    /**
     * Install table before usage.
     *
     * @return null
     */
    public function runInstall()
    {
        $this->start();

        $this->mysql()->multi_query(
            'CREATE TABLE IF NOT EXISTS revision (revision INT UNSIGNED NOT NULL DEFAULT 0);'
                .
            'TRUNCATE TABLE revision;'
                .
            'INSERT INTO revision VALUES (0);'
        );

        if ($this->mysql()->errno) {
            echo COLOR_RED . ">>> Error:\n" . $this->mysql()->error . "\n";
            $this->rollback();
        }
        else {
            echo COLOR_GREEN . ">>> Installed successfully! \n";
            $this->commit();
        }

        $this->mysql()->close();
    }

    /**
     * Get current database revision
     *
     * @return int
     */
    public function revision()
    {
        $resource = $this->mysql()->query('SELECT ' . DB_TABLE . ' FROM revision LIMIT 1');
        if ($this->mysql()->error) {
            echo COLOR_RED . ">>> Please run install first or make sure the revision table is created properly!\n\n";
            exit;
        }
        $row = $resource->fetch_row();
        return $row[0];
    }

    /**
     * Update database revision
     *
     * @param int  $revision  Revision number.
     *
     * @return null
     */
    protected function updateRevision($revision)
    {
        $this->mysql()->query("UPDATE " . DB_TABLE . " SET revision = {$revision};");
    }

    /**
     * Get available deltas.
     *
     * @return array
     */
    protected function deltas()
    {
        $deltas = array();

        foreach (glob(PATH_TO_DELTAS . '*.sql') as $file) {
            $fileName = basename($file);
            $revision = (int)$fileName;
            $deltas[$revision] = $fileName;
        }

        return $deltas;
    }

    /**
     * Start transaction.
     *
     * @return null
     */
    protected function start()
    {
        $this->mysql()->query('START TRANSACTION;');
    }

    /**
     * Commit transaction.
     *
     * @return null
     */
    protected function commit()
    {
        $this->mysql()->query('COMMIT;');
    }

    /**
     * Rollback transaction.
     *
     * @return null
     */
    protected function rollback()
    {
        $this->mysql()->query('ROLLBACK;');
    }

    /**
     * Set foreign key checks.
     *
     * @param bool  $foreignKeyCheck  True for enabling fk checks - false to disable checks.
     *
     * @return null
     */
    protected function fkCheck($foreignKeyCheck = false)
    {
        $this->mysql()->query('SET foreign_key_checks = ' . (int)$foreignKeyCheck . ';');
    }

    /**
     * Clean results after multi-query.
     *
     * @see http://dev.mysql.com/doc/refman/5.0/en/commands-out-of-sync.html
     *
     * @return null
     */
    protected function cleanResults()
    {
        while ($this->mysql()->more_results() && $this->mysql()->next_result()) {
            if ($this->mysql()->use_result()) {
                $this->mysql()->use_result()->free_result();
            }
        }
    }
}

new MySQLRevisioningTool($argv);
