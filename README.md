# MySQL Revisioning Tool v0.1


## Intro

This tool will help you keep an up-to-date revision of your mysql database structure (DDL).

Every member of your team MUST commit a delta sql file with DDL changes and the corresponding rollback.
Examples:
- `CREATE TABLE ...` and `DROP TABLE ...`
- `ALTER TABLE ADD COLUMN ...` and `ALTER TABLE DROP COLUMN ...`


## Requirements

- PHP 5 with mysqli extension.
- Obviously a mysql server installed.


## Before installing

First you need to change the config.php file with the correct database credentials.


## Installing

Run this in the command line:

`./mrt.sh install`

This will create a table in your database with revision 0. 
This way the script will know each time what version you have.


## Help

For usage help run this command:

`./mrt.sh help`


## Update

Update to the latest revision:

`./mrt.sh update`


## Update/Revert 

Update/Revert to a specific revision:

`./mrt.sh revision 7`

If provided revision is higher than db revision it will run update to that revision, otherwise it will rollback.


## Revisions (delta scripts)

### SQL

Each delta script is a sql file containing a database change and a rollback to previous revision.

For the script to understand the difference you need to add `--@UNDO` between update and revert sql's.

Example:

```sql
-- Creating the dummy table (developer@domain.com)

CREATE TABLE IF NOT EXISTS demo (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(20) NOT NULL);

INSERT INTO demo VALUES (1, 'one');

--@UNDO

DROP TABLE IF EXISTS dummy;
```

### Naming conventions

1) The following is an example for the first delta: `0001-My_first_delta.sql`.

You should take in the consideration the number of revisions that might occur during the project development.

For previous example it will have a limit of 9999 revisions.

2) Note: If you start with '01' and you have more than 100 changes then prefix all deltas with a '0' so '01' becomes
'001' and so on until '099'. This will not have an impact on future changes.


## Limitations

1) This is not a replication script!

2) It is only designed to keep the DDL synchronized on multiple development databases.

3) In case you need a DML sync (INSERT, UPDATE, DELETE etc) this can only be achieved by writing better rollback
queries but keep in mind that it won't be suitable for all use cases, mainly for static tables with rare changes.


## Best Practices

1) Try to use statements like `CREATE TABLE IF NOT EXISTS` or `INSERT IGNORE` to avoid possible issues.

2) If you're using a VCS try to create hooks on pulling/pushing code on repository server to run the
`./mrt.sh update` command automatically.

3) Once a delta is commited to VCS you should add another delta to change a previous commited one.


## Demo

Once you've cloned the tool and changed config.php you can try the demo deltas to get familiar with the tool:

1) Install first: `./mrt.sh install` - this will install the revisioning table in the database.

2) Run update: `./mrt.sh update` - this will add both deltas to the database 
(creating dummy table and inserting the rows)

3) Revert: `./mrt.sh revision 1` - this will revert the second delta and revert the db to revision 1.

4) Update again: `./mrt.sh update` - this will trigger the update command and run the second delta again.

5) Trying an update again: `./mrt.sh update` - this will tell you that you have the latest database revision.
