<?php
/**
 * Laravel Facade Cheat Sheet generator
 *
 *
 * @author    Geoff Saulmon <saulmon@gmail.com>
 *
 * laravel-ide-helper was used as the base for this, so the original author/project info is below
 *
 * @author    Barry vd. Heuvel <barryvdh@gmail.com>
 * @copyright 2013 Barry vd. Heuvel / Fruitcake Studio (http://www.fruitcakestudio.nl)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      https://github.com/barryvdh/laravel-ide-helper
 */

namespace Gsaulmon\LaravelFacadeDump;

use Illuminate\Console\Command;
use Illuminate\Foundation\AliasLoader;
use phpDocumentor\Reflection\DocBlock\Context;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlock\Tag;
use Symfony\Component\Console\Input\InputArgument;

class GeneratorCommand extends Command
{

    protected $name = 'facade-dump';
    protected $description = 'Generate a new JSON dump of the Facade structure file.';
    protected $extra;
    protected $onlyExtend;
    protected $helpers;
    protected $magic;
    protected $overrides;
    protected $mapping;

    public function fire()
    {
        if (file_exists($compiled = base_path() . '/bootstrap/compiled.php')) {
            $this->error('Error generating mapping: first delete bootstrap/compiled.php (php artisan clear-compiled)');
        } else {
            $filename = $this->argument('filename');

            //Use a sqlite database in memory, to avoid connection errors on Database facades
            \Config::set(
                'database.connections.sqlite',
                array(
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                )
            );
            \Config::set('database.default', 'sqlite');

            $this->extra = \Config::get('laravel-facade-dump::extra');
            $this->magic = \Config::get('laravel-facade-dump::magic');
            $this->overrides = \Config::get('laravel-facade-dump::overrides');
            $this->helpers = \Config::get('laravel-facade-dump::helper_files');

            $this->generateDocs();
            $this->processOverrides();

            $content = json_encode($this->mapping);

            $written = \File::put($filename, $content);

            if ($written !== false) {
                $this->info("A mapping was written to $filename");
            } else {
                $this->error("The mapping could not be created at $filename");
            }
        }
    }

    /**
     * Get the parameters and format them correctly
     *
     * @param $method
     * @return array
     */
    public function getParameters($method)
    {
        //Loop through the default values for paremeters, and make the correct output string
        $params = array();
        $paramsWithDefault = array();
        foreach ($method->getParameters() as $param) {
            $paramStr = '$' . $param->getName();
            $params[] = $paramStr;
            if ($param->isOptional()) {
                $default = $param->getDefaultValue();
                if (is_bool($default)) {
                    $default = $default ? 'true' : 'false';
                } elseif (is_array($default)) {
                    $default = 'array()';
                } elseif (is_null($default)) {
                    $default = 'null';
                } elseif (is_int($default)) {
                    //$default = $default;
                } else {
                    $default = "'" . trim($default) . "'";
                }
                $paramStr .= " = $default";
            }
            $paramsWithDefault[] = $paramStr;
        }
        return array($params, $paramsWithDefault);
    }

    public function getDriver($alias)
    {
        try {
            if ($alias == "Auth") {
                $driver = \Auth::driver();
            } elseif ($alias == "DB") {
                $driver = \DB::connection();
            } elseif ($alias == "Cache") {
                $driver = \Cache::driver();
            } elseif ($alias == "Queue") {
                $driver = \Queue::connection();
            } else {
                return false;
            }

            return get_class($driver);
        } catch (\Exception $e) {
            $this->error("Could not determine driver/connection for $alias.");
            return false;
        }
    }

    /**
     * Generate the docs for all facades in the AliasLoader
     *
     * @return string
     */
    protected function generateDocs()
    {

        $aliasLoader = AliasLoader::getInstance();

        //Get all aliases
        $aliases = $aliasLoader->getAliases();

        foreach ($aliases as $alias => $facade) {

            $root = $this->getRoot($facade);
            if (!$root) {
                continue;
            }

            try {

                $this->mapping[$alias] = array(
                    'root' => $root,
                    'facade' => $facade,
                    'methods' => array(),
                );

                $usedMethods = array();

                $this->getMethods($root, $alias, $usedMethods);

                $driver = $this->getDriver($alias);
                if ($driver) {
                    $this->getMethods($driver, $alias, $usedMethods);
                }

                //Add extra methods, from other classes (magic static calls)
                if (array_key_exists($alias, $this->extra)) {
                    $this->mapping[$alias]['root'] = $this->extra[$alias][0];
                    $this->getMethods($this->extra[$alias], $alias, $usedMethods);
                }

                //Add extra methods, from other classes (magic static calls)
                if (array_key_exists($alias, $this->magic)) {
                    $this->addMagicMethods($this->magic[$alias], $alias, $usedMethods);
                }

            } catch (\Exception $e) {
                $this->error("Exception: " . $e->getMessage() . "\nCould not analyze $root.");
            }

        }

        if (!empty($this->helpers)) {
            foreach ($this->helpers as $helper) {
                if (file_exists($helper)) {
                    $helper_raw = str_replace(array('<?php', '?>'), '', \File::get($helper));

                    if (preg_match_all("/function_exists\('(.*)'\)/", $helper_raw, $helper_names, PREG_SET_ORDER)) {
                        foreach ($helper_names as $h) {
                            $reflection = new \ReflectionFunction($h[1]);
                            list($params, $paramsWithDefault) = $this->getParameters($reflection);
                            $phpdoc = new DocBlock($reflection);

                            $info = array(
                                'name' => $h[1],
                                'params' => implode($paramsWithDefault, ", "),
                                'desc' => $phpdoc->getShortDescription(),
                            );

                            $this->mapping['_helpers'][$h[1]] = $info;
                        }
                    }
                }
            }
        }
    }

    /**
     * Get the real root of a facade
     *
     * @param $facade
     * @return bool|string
     */
    protected function getRoot($facade)
    {
        try {
            //If possible, get the facade root
            if (method_exists($facade, 'getFacadeRoot')) {
                $root = get_class($facade::getFacadeRoot());
            } else {
                $root = $facade;
            }

            //If it doesn't exist, skip it
            if (!class_exists($root) && !interface_exists($root)) {
                $this->error("Class $root is not found.");
                return false;
            }

            return $root;

        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage() . "\nSkipping $facade.");
            return false;
        }
    }

    protected function getMethods($classes, $alias, &$usedMethods)
    {
        if (!is_array($classes)) {
            $classes = array($classes);
        }

        foreach ($classes as $class) {
            if (!class_exists($class) && !interface_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);

            $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
            if ($methods) {
                foreach ($methods as $method) {
                    if (!in_array($method->name, $usedMethods)) {

                        $this->parseMethod($method, $alias, $reflection);

                        $usedMethods[] = $method->name;
                    }
                }
            }
        }
    }

    /**
     * @param \ReflectionMethod $method
     * @param string $alias
     * @param $class
     * @param null $methodName
     * @return string
     */
    protected function parseMethod($method, $alias, $class, $methodName = null)
    {

        // Don't add the __clone()/__construct()/etc functions
        if (substr($method->name, 0, 2) == '__') {
            return;
        }
        $methodName = $methodName ? : $method->name;

        $namespace = $method->getDeclaringClass()->getNamespaceName();

        $phpdoc = new DocBlock($method, new Context($namespace));
        $this->normalizeDescription($phpdoc, $method);

        //Get the parameters, including formatted default values
        list($params, $paramsWithDefault) = $this->getParameters($method);

        $declaringClass = $method->getDeclaringClass();
        $root = $class->getName();

        if ($declaringClass->name != $root) {
            $root = $declaringClass->name;
        }

        $info = array(
            'name' => $methodName,
            'params' => implode($paramsWithDefault, ", "),
            'desc' => $phpdoc->getShortDescription(),
        );

        $this->mapping[$alias]['methods'][$root][$methodName] = $info;
    }

    protected function normalizeDescription(&$phpdoc, $method){
        //Get the short + long description from the DocBlock
        $description = $phpdoc->getText();

        //Loop through parents/interfaces, to fill in {@inheritdoc}
        if(strpos($description, '{@inheritdoc}') !== false){
            $inheritdoc = $this->getInheritDoc($method);
            $inheritDescription = $inheritdoc->getText();

            $description = str_replace('{@inheritdoc}', $inheritDescription, $description);
            $phpdoc->setText($description);

            //Add the tags that are inherited
            $inheritTags = $inheritdoc->getTags();
            if($inheritTags){
                foreach($inheritTags as $tag){
                    $tag->setDocBlock();
                    $phpdoc->appendTag($tag);
                }
            }
        }
    }

    protected function getInheritDoc($reflectionMethod){
        $parentClass = $reflectionMethod->getDeclaringClass()->getParentClass();

        //Get either a parent or the interface
        if($parentClass){
            $method = $parentClass->getMethod($reflectionMethod->getName());
        }else{
            $method = $reflectionMethod->getPrototype();
        }
        if($method){
            $phpdoc = new DocBlock($method);
            if(strpos($phpdoc->getText(), '{@inheritdoc}') !== false ){
                //Not at the end yet, try another parent/interface..
                return $this->getInheritDoc($method);
            }else{
                return $phpdoc;
            }
        }
    }

    protected function addMagicMethods($methods, $alias, &$usedMethods)
    {

        foreach ($methods as $magic => $real) {
            list($className, $name) = explode('::', $real);
            if (!class_exists($className) && !interface_exists($className)) {
                continue;
            }
            $method = new \ReflectionMethod($className, $name);
            $class = new \ReflectionClass($className);

            if (!in_array($method->name, $usedMethods)) {

                $this->parseMethod($method, $alias, $class, $magic);

                $usedMethods[] = $method->name;
            }

            $usedMethods[] = $magic;
        }
    }

    protected function processOverrides()
    {
        if (!empty($this->overrides)) {
            foreach ($this->overrides as $loc => $val) {
                array_set($this->mapping, $loc, $val);
            }
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('filename', InputArgument::OPTIONAL, 'The path to the helper file', \Config::get('laravel-facade-dump::filename')),
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array();
    }

}
