# Absl - PHP Database Abstraction Library

> Absl (PHP Database Abstraction Library) is a lightweight and flexible library designed to simplify database operations in PHP applications. It provides a convenient interface for interacting with various database systems using the PHP Data Objects (PDO) extension.

## Table of Contents
1. Installation
2. Connecting to a Database
3. Defining Tables
4. Working with Tables
5. Retrieving Data
6. Data Manipulation
7. Authentication
8. Data Validation
9. Data Sanitization
10. Conclusion

## Installation
To use Absl in your PHP project, you can install it via Composer, which is a dependency management tool for PHP. Follow the steps below to install Absl using Composer:
1. Ensure you have Composer installed on your system. If you don't have Composer installed, you can download and install it by following the instructions on the official Composer website: https://getcomposer.org/download/
2. Once Composer is installed, navigate to your project directory in the command-line interface.
3. Run the following command in your project directory to install Absl and its dependencies:

```bash
composer require retamayo/absl
```
Composer will download the Absl library and its required dependencies and set up the autoloading for you.
After the installation is complete, you can include the Composer autoloader in your PHP files to start using Absl:

```php
require 'vendor/autoload.php';
```
Make sure to adjust the path to the autoload.php file based on your project structure.
That's it! You have successfully installed Absl in your PHP project using Composer. You can now start using Absl's features and methods by referencing the library in your code.

## Connecting to a Database
Before you can start using Absl, you need to establish a connection to your database. Absl uses the PDO extension, which supports a wide range of database systems such as MySQL, PostgreSQL, SQLite, and more.

To connect to a database, create a new PDO object and pass it to the Absl constructor:

```php
$host = 'localhost';
$dbName = 'your_database_name';
$username = 'your_username';
$password = 'your_password';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbName", $username, $password);
    $absl = new Absl($pdo);
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
    // Handle the connection error gracefully
}
```
Make sure to replace the placeholders ($host, $dbName, $username, $password) with your actual database credentials.

## Defining Tables
Absl allows you to define tables by specifying the table name, primary key, and column details. This step is essential to inform Absl about the structure of your database tables.

To define a table, use the defineTable() method:

```php
$tableName = 'users';
$primaryKey = 'id';
$columns = ['column1', 'column2', 'column3'];

$absl->defineTable($tableName, $primaryKey, $columns);
```
Repeat the defineTable() method for each table in your database.

## Working with Tables
To perform database operations on a specific table, you need to select the table first using the useTable() method:

```php
$tableName = 'users';

try {
    $absl->useTable($tableName);
} catch (Exception $e) {
    echo "Table selection failed: " . $e->getMessage();
    // Handle the error gracefully
}
```
Once a table is selected, Absl will use the defined table structure for subsequent operations.

## Retrieving Data
Absl provides several methods to retrieve data from the selected table:

To fetch all records from the table:
```php
$records = $absl->list();
```

You can also specify which columns to fetch:
```php
$records = $absl->list(['column1', 'column2']);
```

To fetch a single record based on a unique column:
```php
$record = $absl->fetch(['column1', 'column2'], 'unique_column_name', 'unique_column_value');
```

To fetch all records from the table in JSON format:
```php
$jsonData = $absl->listJSON();
```

To fetch a single record based on a unique column in JSON format:
```php
$record = $absl->fetchJSON(['column1', 'column2'], 'unique_column_name', 'unique_column_value');
```

## Data Manipulation
Absl simplifies data manipulation operations such as creating new records, updating existing records, and deleting records.

To create a new record:
```php
$data = [
    'name' => 'John Doe',
    'email' => 'john@example.com',
    // Set other column values as needed
];

$newRecordId = $absl->create($data);
```

Note: When creating a new record with a password make sure to hash it and use password_hash() method and use PASSWORD_DEFAULT as the hashing algorithm, otherwise the authentication() method might not work as expected.

To update an existing record:
```php
$where = 'id';
$whereValue = '1';
$data = [
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    // Update other column values as needed
];

$updatedRows = $absl->update($data, $where, $whereValue);
```

To delete a record:
```php
$where = 'id';
$whereValue = '1';
$deletedRows = $absl->delete($where, $whereValue);
```

Note : The create, update, and delete methods returns true or false depending on the status of the operation.

Authentication
Absl provides an authenticate() method to validate user credentials stored in the database. You can use this method to implement user authentication functionality in your PHP application.

```php
$username = 'john@example.com';
$password = 'password123';

if ($absl->authenticate(['column_name_of_username_or_email' => $username, 'column_name_of_password' => $password])) {
    // Authentication successful
} else {
    // Authentication failed
}
```
Make sure to store passwords securely by using techniques like hashing and salting.

Note: When passing credentials array to the authenticate function make sure to put the password index last.

## Data Validation
Absl includes a checkDuplicate() method to validate the uniqueness of values in a specified column. This can be helpful for ensuring data integrity and preventing duplicate entries in your database.

```php
$columnName = 'email';
$columnValue = 'john@example.com';

if ($absl->checkDuplicate($columnName, $columnValue)) {
    // Value is already present in the column
} else {
    // Value is unique
}
```

## Data Sanitization
Absl automatically sanitizes input values and prevent common security vulnerabilities such as SQL injection and cross-site scripting (XSS).

## Conclusion
This documentation provides a basic overview of how to use Absl, a PHP database abstraction library, to simplify database operations in your PHP applications. You can explore the library further to discover additional features and advanced usage scenarios.


