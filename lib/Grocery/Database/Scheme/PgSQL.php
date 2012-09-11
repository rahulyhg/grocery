<?php

namespace Grocery\Database\Scheme;

class PgSQL extends \Grocery\Database\SQL\Scheme
{

  public static $random = 'RANDOM()';

  public static $types = array(
                  'CHARACTER' => 'string',
                  'VARCHAR' => 'string',
                  'CHAR' => 'string',
                  'INT' => 'integer',
                  'BIGINT' => 'integer',
                  'SMALLINT' => 'integer',
                  'BOOLEAN' => 'boolean',
                  'DECIMAL' => 'numeric',
                  'MONEY' => 'numeric',
                  'ZONE' => 'numeric',
                  'DOUBLE' => 'float',
                  'REAL' => 'float',
                  'BLOB' => 'binary',
                );

  public static $raw = array(
                  'primary_key' => 'SERIAL PRIMARY KEY',
                  'string' => array('type' => 'CHARACTER varying', 'length' => 255),
                  'datetime'=> 'TIMESTAMP',
                );



  public function rename($from, $to)
  {
    $this->execute(sprintf('ALTER TABLE "%s" RENAME TO "%s"', $from, $to));
    return $this->remove_index($to, "{$from}_pkey");
  }

  public function add_column($to, $name, $type)
  {
    return $this->execute(sprintf('ALTER TABLE "%s" ADD COLUMN "%s" %s', $to, $name, $this->build_field($type)));
  }

  public function remove_column($from, $name)
  {
    return $this->execute(sprintf('ALTER TABLE "%s" DROP COLUMN "%s" RESTRICT', $from, $name));
  }

  public function rename_column($from, $name, $to)
  {
    return $this->execute(sprintf('ALTER TABLE "%s" RENAME COLUMN "%s" TO "%s"', $from, $name, $to));
  }

  public function change_column($from, $name, $to)
  {
    return $this->execute(sprintf('ALTER TABLE "%s" ALTER COLUMN "%s" TYPE %s', $from, $name, $this->build_field($to)));
  }

  public function add_index($to, $name, $column, $unique = FALSE)
  {
    return $this->execute(sprintf('CREATE%sINDEX "%s" ON "%s" ("%s")', $unique ? ' UNIQUE ' : ' ', $name, $to, join('", "', $column)));
  }

  public function remove_index($from, $name)
  {
    return $this->execute(sprintf('DROP INDEX "%s"', $name));
  }

  public function begin_transaction()
  {
    return $this->execute('BEGIN');
  }

  public function commit_transaction()
  {
    return $this->execute('COMMIT');
  }

  public function rollback_transaction()
  {
    return $this->execute('ROLLBACK');
  }

  public function set_encoding()
  {
    return $this->execute("SET NAMES 'UTF-8'");
  }

  public function fetch_tables()
  {
    $out = array();

    $sql = "SELECT tablename FROM pg_tables WHERE tablename "
         . "!~ '^pg_+' AND schemaname = 'public'";

    $old = $this->execute($sql);

    while ($row = $this->fetch_assoc($old)) {
      $out []= $row['tablename'];
    }

    return $out;
  }

  public function fetch_columns($test)
  {
    $out = array();

    $sql = "SELECT DISTINCT "
         . "column_name, data_type AS t, character_maximum_length, column_default AS d,"
         . "is_nullable FROM information_schema.columns WHERE table_name='$test'";

    $old = $this->execute($sql);

    while ($row = $this->fetch_assoc($old)) {
      if (preg_match('/^nextval\(.+$/', $row['d'], $id)) {
        $row['d'] = NULL;
      } else {
        $row['d'] = trim(preg_replace('/::.+$/', '', $row['d']), "'");
      }

      $test     = explode(' ', $row['t']);
      $row['t'] = $test[0];

      $key  = array_shift($row);
      $type = array_shift($row);

      $out[$key] = array(
        'type' => $id ? 'PRIMARY_KEY' : strtoupper($type),
        'length' => (int) array_shift($row),
        'default' => trim(array_shift($row), "(')"),
        'not_null' => ! array_shift($row),
      );
    }

    return $out;
  }

  public function fetch_indexes($test)
  {
    $out = array();

    $sql = "select pg_get_indexdef(indexrelid) AS sql from pg_index where indrelid = '$test'::regclass";

    if ($res = $this->execute($sql)) {
      while ($one = $this->fetch_assoc($res)) {
        if (preg_match('/CREATE(\s+UNIQUE|)\s+INDEX\s+(\w+)\s+ON.+?\((.+?)\)/', $one['sql'], $match)) {
          $out[$match[2]] = array(
            'unique' => ! empty($match[1]),
            'column' => explode(',', preg_replace('/["\s]/', '', $match[3])),
          );
        }
      }
    }

    return $out;
  }

  public function quote_string($test)
  {
    return '"' . $test . '"';
  }

  public function ensure_limit($test)
  {
    return ! empty($test[2]) ? "\nLIMIT $test[2] OFFSET $test[1]" : "\nLIMIT $test[1]\n";
  }

  public function ensure_type($test)
  {
    if (is_bool($test)) {
      $test = $test ? 'TRUE' : 'FALSE';
    } elseif ($test === NULL) {
      $test = 'NULL';
    }
    return $test;
  }

}
