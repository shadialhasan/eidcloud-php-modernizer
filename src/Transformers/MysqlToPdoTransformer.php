<?php

declare(strict_types=1);

namespace EidCloud\PhpModernizer\Transformers;

class MysqlToPdoTransformer implements TransformerInterface
{
    public function getName(): string
    {
        return 'mysql_to_pdo';
    }

    public function getDescription(): string
    {
        return 'Transforms obsolete mysql_* procedural calls to modern PDO queries and prepared statements';
    }

    public function transform(string $source): string
    {
        // 1. mysql_connect(host, user, pass) -> new \PDO("mysql:host=host", user, pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION])
        $source = preg_replace_callback(
            '/\bmysql_connect\s*\(\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"]|\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*,\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"]|\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*,\s*([' . '\'' . '"][^' . '\'' . '"]*[' . '\'' . '"]|\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)/i',
            function ($matches) {
                $host = trim($matches[1], "'\"");
                $user = $matches[2];
                $pass = $matches[3];
                if (str_starts_with($matches[1], '$')) {
                    $dsn = "\"mysql:host={\$host}\"";
                } else {
                    $dsn = "'mysql:host={$host}'";
                }
                return "new \\PDO({$dsn}, {$user}, {$pass}, [\\PDO::ATTR_ERRMODE => \\PDO::ERRMODE_EXCEPTION, \\PDO::ATTR_DEFAULT_FETCH_MODE => \\PDO::FETCH_ASSOC])";
            },
            $source
        );

        // 2. mysql_select_db(db, link) -> $pdo->exec("USE db")
        $source = preg_replace_callback(
            '/\bmysql_select_db\s*\(\s*([' . '\'' . '"][^' . '\'' . '"]+[' . '\'' . '"]|\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)(?:\s*,\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?\s*\)/i',
            function ($matches) {
                $db = $matches[1];
                $pdo = !empty($matches[2]) ? $matches[2] : '$pdo';
                if (str_starts_with($db, "'") || str_starts_with($db, '"')) {
                    $rawDb = trim($db, "'\"");
                    return "{$pdo}->exec(\"USE `{$rawDb}`\")";
                }
                return "{$pdo}->exec(\"USE `\" . {$db} . \"`\")";
            },
            $source
        );

        // 3. mysql_query(query, link) -> $pdo->query(query)
        $source = preg_replace_callback(
            '/\bmysql_query\s*\(\s*(.+?)(?:\s*,\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?\s*\)/i',
            function ($matches) {
                $query = trim($matches[1]);
                $pdo = !empty($matches[2]) ? $matches[2] : '$pdo';
                return "{$pdo}->query({$query})";
            },
            $source
        );

        // 4. mysql_fetch_assoc(res) -> res->fetch(\PDO::FETCH_ASSOC)
        $source = preg_replace_callback(
            '/\bmysql_fetch_assoc\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)/i',
            function ($matches) {
                $res = $matches[1];
                return "{$res}->fetch(\\PDO::FETCH_ASSOC)";
            },
            $source
        );

        // 5. mysql_fetch_array(res) -> res->fetch(\PDO::FETCH_BOTH)
        $source = preg_replace_callback(
            '/\bmysql_fetch_array\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)/i',
            function ($matches) {
                $res = $matches[1];
                return "{$res}->fetch(\\PDO::FETCH_BOTH)";
            },
            $source
        );

        // 6. mysql_num_rows(res) -> res->rowCount()
        $source = preg_replace_callback(
            '/\bmysql_num_rows\s*\(\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\s*\)/i',
            function ($matches) {
                $res = $matches[1];
                return "{$res}->rowCount()";
            },
            $source
        );

        // 7. mysql_insert_id(link) -> $pdo->lastInsertId()
        $source = preg_replace_callback(
            '/\bmysql_insert_id\s*\((?:\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?\s*\)/i',
            function ($matches) {
                $pdo = !empty($matches[1]) ? $matches[1] : '$pdo';
                return "{$pdo}->lastInsertId()";
            },
            $source
        );

        // 8. mysql_real_escape_string(val, link) -> $pdo->quote(val)
        $source = preg_replace_callback(
            '/\bmysql_real_escape_string\s*\(\s*(.+?)(?:\s*,\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?\s*\)/i',
            function ($matches) {
                $val = trim($matches[1]);
                $pdo = !empty($matches[2]) ? $matches[2] : '$pdo';
                return "{$pdo}->quote({$val})";
            },
            $source
        );

        // 9. mysql_close(link) -> link = null
        $source = preg_replace_callback(
            '/\bmysql_close\s*\((?:\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?\s*\)/i',
            function ($matches) {
                $pdo = !empty($matches[1]) ? $matches[1] : '$pdo';
                return "({$pdo} = null)";
            },
            $source
        );

        // 10. mysql_error(link) -> implode(": ", $pdo->errorInfo())
        $source = preg_replace_callback(
            '/\bmysql_error\s*\((?:\s*(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*))?\s*\)/i',
            function ($matches) {
                $pdo = !empty($matches[1]) ? $matches[1] : '$pdo';
                return "implode(\": \", {$pdo}->errorInfo())";
            },
            $source
        );

        return $source;
    }
}
