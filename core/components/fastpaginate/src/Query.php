<?php

namespace Boshnik\FastPaginate;

class Query
{
    public string $table;

    public function __construct(
        private \modX $modx,
        private array &$properties
    ) {
        $this->update($properties);
    }

    public function update(array $properties = []): void
    {
        $this->properties = $properties;
        $className = $properties['className'];
        $this->table = $this->modx->getTableName($className) ?? $className;
    }

    public function getData(array $where = [])
    {
        $whereSQL = $this->createWhere($where);
        $subquery = $this->getSubQuery($whereSQL);

        $fields = array_map(function ($field) {
            return "main.$field";
        }, explode(',', $this->properties['fields']));
        $fields = implode(',', $fields);

        $sql = "
            SELECT {$fields}
            FROM {$this->table} AS main
            JOIN (
                $subquery
            ) AS subquery ON main.id = subquery.id
        ";
        $stmt = $this->modx->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getTotal(array $where = []): int
    {
        $whereSQL = $this->createWhere($where);
        $sql = "SELECT COUNT(*) AS total_records FROM {$this->table} {$whereSQL}";
        $stmt = $this->modx->query($sql);
        if ($stmt) {
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int) ($result['total_records'] ?? 0);
        }

        return 0;
    }

    public function getSubQuery(string $where = ''): string
    {
        $limit = $this->properties['limit'];
        $offset = $this->properties['offset'];
        $sortby = $this->properties['sortby'];
        $sortdir = $this->properties['sortdir'];

        return "
            SELECT `id` 
            FROM {$this->table} 
            {$where} 
            ORDER BY `$sortby` $sortdir
            LIMIT $limit 
            OFFSET $offset 
        ";
    }

    public function createWhere(array $filters = []): string
    {
        $where = [];
        foreach ($filters as $field => $value) {
            if (strpos($field, ':') !== false) {
                list($fieldName, $operator) = explode(':', $field, 2);
                switch ($operator) {
                    case '>':
                        $where[] = "`$fieldName` > " . $this->modx->quote($value);
                        break;
                    case '<':
                        $where[] = "`$fieldName` < " . $this->modx->quote($value);
                        break;
                    case '>=':
                        $where[] = "`$fieldName` >= " . $this->modx->quote($value);
                        break;
                    case '<=':
                        $where[] = "`$fieldName` <= " . $this->modx->quote($value);
                        break;
                    case '!=':
                        $where[] = "`$fieldName` != " . $this->modx->quote($value);
                        break;
                    case '=':
                        $where[] = "`$fieldName` = " . $this->modx->quote($value);
                        break;
                    case 'LIKE':
                        $where[] = "`$fieldName` LIKE " . $this->modx->quote("%$value%");
                        break;
                    case 'NOT LIKE':
                        $where[] = "`$fieldName` NOT LIKE " . $this->modx->quote("%$value%");
                        break;
                    case 'IN':
                        $inValues = implode(',', array_map([$this->modx, 'quote'], $value));
                        $where[] = "`$fieldName` IN ($inValues)";
                        break;
                    case 'NOT IN':
                        $notInValues = implode(',', array_map([$this->modx, 'quote'], $value));
                        $where[] = "`$fieldName` NOT IN ($notInValues)";
                        break;
                    case 'IS':
                        $where[] = "`$fieldName` IS " . ($value === null ? 'NULL' : $this->modx->quote($value));
                        break;
                    case 'IS NOT':
                        $where[] = "`$fieldName` IS NOT " . ($value === null ? 'NULL' : $this->modx->quote($value));
                        break;
                }
            } else {
                $where[] = "`$field` = " . $this->modx->quote($value);
            }
        }

        return !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    }
}