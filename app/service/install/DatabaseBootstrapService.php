<?php
declare(strict_types=1);

namespace app\service\install;

use PDO;

class DatabaseBootstrapService
{
    public function importBootstrapSql(PDO $pdo): void
    {
        $sql = (string) file_get_contents($this->bootstrapSqlPath());

        foreach ($this->splitStatements($sql) as $statement) {
            $trimmed = trim($statement);
            if ($trimmed === '') {
                continue;
            }

            $pdo->exec($trimmed);
        }
    }

    protected function bootstrapSqlPath(): string
    {
        return app()->getRootPath() . 'vmq.sql';
    }

    /**
     * @return list<string>
     */
    protected function splitStatements(string $sql): array
    {
        $lines = preg_split('/\R/', $sql) ?: [];
        $statements = [];
        $buffer = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--')) {
                continue;
            }

            $buffer .= $line . PHP_EOL;

            if (str_ends_with(rtrim($line), ';')) {
                $statements[] = trim($buffer);
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }
}
