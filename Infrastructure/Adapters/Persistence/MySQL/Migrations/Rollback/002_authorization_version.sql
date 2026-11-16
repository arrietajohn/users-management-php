-- Roll back only after deploying application code that no longer reads this column.
ALTER TABLE users DROP COLUMN authorization_version;
