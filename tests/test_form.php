<?php
    $db_name = 'wordpress';
    $db_user = 'root';
    $db_pass = '';
    $db_host = 'localhost';

    $tables_to_check = array('wp_fastfields', 'wp_fastfieldsmeta',
                             'wp_fastcarts', 'wp_fastcartsmeta',
                             'wp_fastcartssales');
?>
<html>
    <head>
        <style type="text/css">
            table
            {
                border-collapse: collapse;
            }
            td
            {
                border: solid black 1px;
            }
        </style>
    </head>
    <body>
        <div>
            <form action="" method="get">
                <input type="submit" name="submit"
                       value="Reset Databases" />
                <input type="submit" name="submit"
                       value="Describe Tables" />
                <input type="submit" name="submit"
                       value="Check Tables" />
                <input type="submit" name="submit"
                       value="Backup Tables" />
            </form>
        </div>
<?php

    function dump_mysql_result($result) {
        if (!$result) {
            echo '<strong style="color:red">';
            echo mysql_error();
            echo '</strong>';
        } else {
            echo "<table>\n<thead>\n<tr>\n";

            $row = mysql_fetch_assoc($result);

            if ($row) {
                foreach ($row as $key => $value) {
                    echo "<td>{$key}</td>\n";
                }

                echo "</tr>\n</thead>\n<tbody>\n";

                do {
                    echo "<tr>\n";
                    foreach ($row as $key => $value) {
                        echo "<td>{$value}</td>\n";
                    }
                    echo "</tr>\n";
                } while ($row = mysql_fetch_assoc($result));

                echo "</tbody>\n</table>\n";
            } else {
                echo '<p>Table is empty.</p>';
            }
        }
    }

    if (isset ($_GET['submit'])) {

        mysql_connect($db_host, $db_user, $db_pass);
        mysql_select_db($db_name);

        $action = $_GET['submit'];

        if ('Reset Databases' == $action) {
            foreach ($tables_to_check as $table) {
                $sql = 'DROP TABLE ' . $table . ';';
                $result = mysql_query($sql);
                echo "<h6>{$sql}</h6>\n";
                if (!$result) {
                    echo '<strong style="color:red">';
                    echo mysql_error();
                    echo '</strong>';
                } else {
                    echo "Table dropped\n";
                }
            }
        }

        if ('Describe Tables' == $action) {
            foreach ($tables_to_check as $table) {
                $sql = 'DESCRIBE ' . $table . ';';
                $result = mysql_query($sql);
                echo "<h6>{$sql}</h6>\n";
                dump_mysql_result($result);
            }
        }

        if ('Check Tables' == $action) {
            foreach ($tables_to_check as $table) {
                $sql = 'SELECT * FROM ' . $table . ';';
                $result = mysql_query($sql);
                echo "<h6>{$sql}</h6>\n";
                dump_mysql_result($result);
            }
            $sql = 'SELECT *
                   FROM wp_postmeta
                   WHERE meta_key LIKE \'fast_fields_%\';';
            $result = mysql_query($sql);
            echo "<h6>{$sql}</h6>\n";
            dump_mysql_result($result);
            $sql = 'SELECT *
                   FROM wp_postmeta
                   WHERE meta_key LIKE \'fast_products_%\';';
            $result = mysql_query($sql);
            echo "<h6>{$sql}</h6>\n";
            dump_mysql_result($result);
        }

        if ('Backup Tables' == $action
                || 'Restore Tables' == $_POST['submit']) {


            echo '<div><form action="" method="post">';

            foreach ($tables_to_check as $table) {

                if ('Restore Tables' == $_POST['submit']
                        && !$table_empty) {
                    $sql = $_POST["{$table}query"];
                    mysql_query($sql);
                    echo '<br />' . mysql_error() . '<br />';
                }

                $sql = 'SELECT * FROM ' . $table . ';';
                $result = mysql_query($sql);

                echo "<textarea name=\"{$table}query\"
                                rows=\"20\" cols=\"50\">";

                if ($row = mysql_fetch_assoc($result)) {
                    echo "INSERT INTO {$table}(";

                    $comma = '';
                    foreach ($row as $key => $value) {
                        echo "{$comma}{$key}";
                        $comma = ', ';
                    }

                    echo ") VALUES";

                    $comma = "\n";
                    do {
                        echo "{$comma}(";
                        $comma = '';
                        foreach ($row as $value) {
                            if ($value == '') {
                                echo "{$comma}NULL";
                            } else {
                                echo "{$comma}'{$value}'";
                            }
                            $comma = ', ';
                        }
                        echo ')';
                        $comma = ",\n";
                    } while ($row = mysql_fetch_assoc($result));

                    echo ";\n";
                } else {
                    echo "Table '{$table}' is empty.";
                }

                echo '</textarea>';
            }
            echo '<br /><input type="submit" name="submit"
                 value="Restore Tables" /></form></div>';
        }
    }

?>
    </body>
</html>
