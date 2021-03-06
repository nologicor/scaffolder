<?php

namespace Scaffolder\Compilers\Core;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Scaffolder\Compilers\AbstractCoreCompiler;
use Scaffolder\Support\FileToCompile;
use Scaffolder\Support\PathParser;
use stdClass;

class MigrationCompiler extends AbstractCoreCompiler
{
    /**
     * Migration date
     * @var \Carbon\Carbon
     */
    private $date;

    /**
     * Create a new migration compiler instance.
     */
    public function __construct()
    {
        $this->date = Carbon::now();
    }

    /**
     * Compiles a migration.
     *
     * @param $stub
     * @param $modelName
     * @param $modelData
     * @param \stdClass $scaffolderConfig
     * @param $hash
     * @param \Scaffolder\Support\Contracts\ScaffolderExtensionInterface[] $extensions
     * @param null $extra
     *
     * @return string
     */
    public function compile($stub, $modelName, $modelData, stdClass $scaffolderConfig, $hash, array $extensions, $extra = null)
    {
        // Add time to migration
        $this->date->addSeconds(5);

        if (File::exists(base_path('scaffolder-config/cache/migration_' . $hash . self::CACHE_EXT)))
        {
            return $this->store($modelName, $scaffolderConfig, '', new FileToCompile(true, $hash));
        }
        else
        {
            $this->stub = $stub;

            $this->replaceClassName($modelName)
                ->replaceTableName($scaffolderConfig, $modelName)
                ->addFields($modelData);

            foreach ($extensions as $extension)
            {
                $this->stub = $extension->runAfterMigrationIsCompiled($this->stub, $modelData, $scaffolderConfig);
            }

            return $this->store($modelName, $scaffolderConfig, $this->stub, new FileToCompile(false, $hash));
        }
    }

    /**
     * Store the compiled stub.
     *
     * @param $modelName
     * @param \stdClass $scaffolderConfig
     * @param $compiled
     * @param \Scaffolder\Support\FileToCompile $fileToCompile
     *
     * @return string
     */
    protected function store($modelName, stdClass $scaffolderConfig, $compiled, FileToCompile $fileToCompile)
    {
        $path = PathParser::parse($scaffolderConfig->paths->migrations) . $this->date->format('Y_m_d_His') . '_create_' . strtolower($modelName) . 's_table.php';

        // Store in cache
        if ($fileToCompile->cached)
        {
            File::copy(base_path('scaffolder-config/cache/migration_' . $fileToCompile->hash . self::CACHE_EXT), $path);
        }
        else
        {
            File::put(base_path('scaffolder-config/cache/migration_' . $fileToCompile->hash . self::CACHE_EXT), $compiled);
            File::copy(base_path('scaffolder-config/cache/migration_' . $fileToCompile->hash . self::CACHE_EXT), $path);
        }

        return $path;
    }

    /**
     * Replace the table name.
     *
     * @param \stdClass $scaffolderConfig
     * @param $modelName
     *
     * @return $this
     */
    private function replaceTableName(stdClass $scaffolderConfig, $modelName)
    {
        $tableName = isset($scaffolderConfig->tableName) && !empty($scaffolderConfig->tableName) ? $scaffolderConfig->tableName : $modelName . 's';

        $this->stub = str_replace('{{table_name}}', strtolower($tableName), $this->stub);

        return $this;
    }

    /**
     * Add fields.
     *
     * @param $modelData
     *
     * @return $this
     */
    private function addFields($modelData)
    {
        // Default primary key
        $fields = $this->tab(3) . "\$table->increments('id');" . PHP_EOL . PHP_EOL;

        // Check primary key
        foreach ($modelData->fields as $field)
        {
            if ($field->index == 'primary')
            {
                $fields = '';
                break;
            }
        }

        foreach ($modelData->fields as $field)
        {
            $parsedModifiers = '';

            // Check modifiers
            if (!empty($field->modifiers))
            {
                $modifiersArray = explode(':', $field->modifiers);

                foreach ($modifiersArray as $modifier)
                {
                    $modifierAndValue = explode(',', $modifier);

                    if (count($modifierAndValue) == 2)
                    {
                        $parsedModifiers .= '->' . $modifierAndValue[0] . '(' . $modifierAndValue[1] . ')';
                    }
                    else
                    {
                        $parsedModifiers .= '->' . $modifierAndValue[0] . '()';
                    }
                }
            }

            // Check indexes
            if ($field->index != 'none')
            {
                $fields .= sprintf($this->tab(3) . "\$table->%s('%s')%s->%s();" . PHP_EOL, $field->type->db, $field->name, $parsedModifiers, $field->index);
            }
            else
            {
                $fields .= sprintf($this->tab(3) . "\$table->%s('%s')%s;" . PHP_EOL, $field->type->db, $field->name, $parsedModifiers);
            }

            // Check foreign key
            if (!empty($field->foreignKey))
            {
                $foreignKey = explode(':', $field->foreignKey);
                $fields .= sprintf($this->tab(3) . "\$table->foreign('%s')->references('%s')->on('%s');" . PHP_EOL . PHP_EOL, $field->name, $foreignKey[0], $foreignKey[1]);
            }
        }

        $fields .= PHP_EOL . $this->tab(3) . "\$table->timestamps();" . PHP_EOL;

        $this->stub = str_replace('{{fields}}', $fields, $this->stub);

        return $this;
    }
}
