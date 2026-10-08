<?php
$sqlite = new PDO('sqlite:database/WebsiteTailors.sqlite');
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mysql = new PDO('mysql:host=127.0.0.1;port=3306;dbname=WebsiteTailors;charset=utf8mb4', 'root', '');
$mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = ['invoices', 'revenue']; // Only retry failed ones if they have data. Or actually, just do all again, but truncate first? Let's do invoices and revenue.

foreach ($tables as $table) {
    try {
        $mysql->exec("TRUNCATE TABLE `$table`");
        $rows = $sqlite->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) {
            echo "No data in $table\n";
            continue;
        }
        
        $mysql_columns_stmt = $mysql->query("DESCRIBE `$table`");
        $mysql_columns = [];
        while ($col = $mysql_columns_stmt->fetch(PDO::FETCH_ASSOC)) {
            $mysql_columns[] = $col['Field'];
        }
        
        $columns = [];
        foreach (array_keys($rows[0]) as $col) {
            if (in_array($col, $mysql_columns)) {
                $columns[] = $col;
            }
        }
        
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        $stmt = $mysql->prepare($sql);
        
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $col) {
                $values[] = $row[$col];
            }
            $stmt->execute($values);
        }
        echo "Migrated $table\n";
    } catch (Exception $e) {
        echo "Error on $table: " . $e->getMessage() . "\n";
    }
}
