<?php

namespace entitys\bd;

use entitys\annotations\Columns;
use entitys\annotations\Entity;
use entitys\bd\ConnectionOnBD;

abstract class EntityOnBD
{
    private \ReflectionClass $entityClass;
    private string $entityName;
    private \PDO $conn;

    public function __construct()
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(dirname(__DIR__, 5));
        $dotenv->load();
        $this->entityClass = new \ReflectionClass(static::class);
        $this->conn = ConnectionOnBD::getConnection();
        $this->setName();
        $this->createTable();
    }

    private function setName(): void
    {
        $entityAnnotation = $this->entityClass->getAttributes(Entity::class);

        if (!empty($entityAnnotation)) {
            $instance = $entityAnnotation[0]->newInstance();
            if ($instance->name != null) {
                $this->entityName = $instance->name;
            } else {
                $this->entityName = $this->entityClass->getShortName();
            }
        } else {
            throw new \Exception("A entidade '" . $this->entityClass->getName() . "' não possui a anotação #[Entity].");
        }
    }

    private function createTable(): void
    {
        $propriedades = $this->entityClass->getProperties();

        foreach ($propriedades as $propriedade) {

            if (empty($propriedade->getAttributes(Columns::class))) {
                throw new \Exception("Propriedade sem atributo Columns");
            }

            $attrs[] = $propriedade->getAttributes(Columns::class);

            $TiposENomesPropriedades[$propriedade->getName()] = $propriedade->getType()->getName();
        }

        foreach ($attrs as $attrArray) {
            foreach ($attrArray as $attr) {
                $instancesColums[] = $attr->newInstance();
            }
        }

        $sql = $this->generateSQL($TiposENomesPropriedades, $instancesColums);

        $this->conn->exec($sql);

        $this->verificarTabela();
    }

    private function generateType(string $sql, Columns $coluna): string
    {
        if ($coluna->primary) {
            $sql .= "PRIMARY KEY ";
        }

        if ($coluna->ai) {
            $sql .= "AUTO_INCREMENT ";
        }

        if ($coluna->unique) {
            $sql .= "UNIQUE";
        }

        return $sql;
    }

    private function generateSQL($TiposENomesPropriedades, $instancesColums)
    {
        $sql = "CREATE TABLE IF NOT EXISTS `$this->entityName` (";

        foreach ($TiposENomesPropriedades as $key => $value) {

            $keys = array_keys($TiposENomesPropriedades);
            $posicao = array_search($key, $keys);

            if (isset($instancesColums[$posicao]->name) && $instancesColums[$posicao]->name != "") {
                $name = $instancesColums[$posicao]->name;
                $sql .= "$name ";
            } else {
                $sql .= "$key ";
            }

            switch ($value) {
                case 'string':
                    $sql .= "VARCHAR(190) ";
                    $sql = $this->generateType($sql, $instancesColums[$posicao]);
                    break;
                case 'int':
                    $sql .= "INT ";
                    $sql = $this->generateType($sql, $instancesColums[$posicao]);
                    break;
                default:
                    throw new \Exception("Tipo de propriedade '$key' não suportado na entidade '{$this->entityName}'.");
                    break;
            }
            if ($posicao < count($TiposENomesPropriedades) - 1) {
                $sql .= ",";
            }
        }

        $sql .= ")";
        return $sql;
    }

    private function verificarTabela()
    {
        $colunasDoBanco = [];
        $sql = "SHOW COLUMNS FROM `$this->entityName`";
        $result = $this->conn->query($sql)->fetchAll();

        foreach ($result as $coluna) {
            $colunasDoBanco[] = $coluna["Field"];
        }

        // Obter atributos da classe com anotação #[Columns]
        $propriedades = $this->entityClass->getProperties();
        $colunasDaClasse = [];

        foreach ($propriedades as $index => $propriedade) {
            $attrs = $propriedade->getAttributes(Columns::class);
            $tipo = $propriedade->getType()->getName();

            if (empty($attrs)) {
                continue;
            }

            $attr = $attrs[0]->newInstance();
            $nomeColuna = !empty($attr->name) ? $attr->name : $propriedade->getName();

            $sqlTipo = match ($tipo) {
                'string' => "VARCHAR(190)",
                'int' => "INT",
                default => throw new \Exception("Tipo $tipo não suportado"),
            };

            $modificadores = [];
            if ($attr->primary) $modificadores[] = "PRIMARY KEY";
            if ($attr->ai) $modificadores[] = "AUTO_INCREMENT";
            if ($attr->unique) $modificadores[] = "UNIQUE";

            $colunasDaClasse[$nomeColuna] = "$sqlTipo " . implode(" ", $modificadores);
        }

        // Adicionar colunas que estão na classe mas não no banco
        foreach ($colunasDaClasse as $nome => $tipoSql) {
            if (!in_array($nome, $colunasDoBanco)) {
                $this->conn->exec("ALTER TABLE `$this->entityName` ADD `$nome` $tipoSql");
            }
        }

        // Remover colunas que estão no banco mas não na classe
        foreach ($colunasDoBanco as $nome) {
            if (!array_key_exists($nome, $colunasDaClasse)) {
                $this->conn->exec("ALTER TABLE `$this->entityName` DROP COLUMN `$nome`");
            }
        }
    }
}
