<?php

namespace Grocery\Database\Scheme;

class SQLite extends \Grocery\Database\SQL\Scheme
{

  public static $random = 'RANDOM()';

  public static $types = array(
                  'CHARACTER' => 'string',
                  'NVARCHAR' => 'string',
                  'VARCHAR' => 'string',
                  'NCHAR' => 'string',
                  'CLOB' => 'string',
                  'INT' => 'integer',
                  'TINYINT' => 'integer',
                  'SMALLINT' => 'integer',
                  'MEDIUMINT' => 'integer',
                  'BIGINT' => 'integer',
                  'INT2' => 'integer',
                  'INT8' => 'integer',
                  'REAL' => 'float',
                  'DOUBLE' => 'float',
                  'DECIMAL' => 'numeric',
                  'BLOB' => 'binary',
                  'DATETIME' => 'timestamp',
                );

  public static $raw = array(
                  'primary_key' => 'INTEGER NOT NULL PRIMARY KEY',
                  'string' => array('type' => 'VARCHAR', 'length' => 255),
                  'timestamp' => 'DATETIME',
                  'binary' => 'BLOB',
                );

  public function rename($from, $to)
  {
    return $this->execute(sprintf('ALTER TABLE "%s" RENAME TO "%s"', $from, $to));
  }

  public function add_column($to, $name, $type)
  {
    return $this->execute(sprintf('ALTER TABLE "%s" ADD COLUMN "%s" %s', $to, $name, $this->build_field($type)));
  }

  public function remove_column($from, $name)
  {
    return $this->change_column($from, $name, NULL);
  }

  public function rename_column($from, $name, $to)
  {
    $set = $this->columns($from);
    $old = $set[$name];

    $this->add_column($from, $to, array(
      $old['type'],
      $old['length'],
      $old['default'],
    ));

    $this->execute(sprintf('UPDATE "%s" SET "%s" = "%s"', $from, $to, $name));

    return $this->remove_column($from, $name);
  }

  public function change_column($from, $name, $to)
  {
    $new = array();

    foreach ($this->columns($from) as $key => $val) {
      if ($key === $name) {
        $to && $new[$key] = (array) $to;
        continue;
      }

      $new[$key] = array(
        $val['type'],
        $val['length'],
        $val['default'],
      );
    }

    $this->begin();

    $this->execute($this->build_table($old = uniqid($from), $new));
    $this->execute(sprintf('INSERT INTO "%s" SELECT "%s" FROM "%s"', $old, join('", "', array_keys($new)), $from));
    $this->execute(sprintf('DROP TABLE "%s"', $from));

    $this->rename($old, $from);

    return $this->commit();
  }

  public function add_index($to, $name, array $column, $unique = FALSE)
  {
    return $this->execute($this->build_index($to, $name, compact('column', 'unique')));
  }

  public function build_index($to, $name, array $params)
  {
    return sprintf('CREATE%sINDEX IF NOT EXISTS "%s" ON "%s" ("%s")', $params['unique'] ? ' UNIQUE ' : ' ', $name, $to, join('", "', $params['column']));
  }

  public function remove_index($from, $name)
  {
    return $this->execute(sprintf('DROP INDEX IF EXISTS "%s"', $name));
  }

  public function begin_transaction()
  {
    return $this->execute('BEGIN TRANSACTION');
  }

  public function commit_transaction()
  {
    return $this->execute('COMMIT TRANSACTION');
  }

  public function rollback_transaction()
  {
    return $this->execute('ROLLBACK TRANSACTION');
  }

  public function fetch_tables()
  {
    $out = array();
    $sql = "SELECT name FROM sqlite_master WHERE type = 'table'";
    $old = $this->execute($sql);

    while ($row = $this->fetch_assoc($old)) {
      $out []= $row['name'];
    }

    return $out;
  }

  public function fetch_columns($test)
  {
    $out = array();
    $sql = "PRAGMA table_info('$test')";
    $old = $this->execute($sql);

    while ($row = $this->fetch_assoc($old)) {
      preg_match('/^(\w+)(?:\((\d+)\))?.*?$/', strtoupper($row['type']), $match);

      $out[$row['name']] = array(
          'type' => $row['pk'] > 0 ? 'PRIMARY_KEY' : $match[1],
          'length' => !empty($match[2]) ? (int) $match[2] : 0,
          'default' => trim($row['dflt_value'], "(')"),
          'not_null' => $row['notnull'] > 0,
      );
    }

    return $out;
  }

  public function fetch_indexes($test)
  {
    $res = $this->execute("SELECT name,sql FROM sqlite_master WHERE type='index' AND tbl_name='$test'");

    $out = array();

    while ($one = $this->fetch_assoc($res)) {
      if (preg_match('/\((.+?)\)/', $one['sql'], $match)) {
        $col = explode(',', preg_replace('/["\s]/', '', $match[1]));
        $out[$one['name']] = array(
          'unique' => strpos($one['sql'], 'UNIQUE ') !== FALSE,
          'column' => $col,
        );
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
    return "\nLIMIT $test[1]" . (!empty($test[2]) ? ",$test[2]" : '');
  }

  public function ensure_type($test)
  {
    if (is_bool($test)) {
      $test = $test ? 1 : 0;
    } elseif ($test === NULL) {
      $test = 'NULL';
    }

    return $test;
  }

}
