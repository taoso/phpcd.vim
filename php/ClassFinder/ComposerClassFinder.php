<?php

namespace PHPCD\ClassFinder;

use Composer\Autoload\ClassLoader;
use Assert\Assertion;
use Psr\Log\LoggerInterface;

use PHPCD\Matcher\ClassMatcher;

/**
 * To find the classes of a project using composer.
 * Manage classmap, PSR-4 and fallback dirs autoloading styles.
 * The PSR-0 standard is not handle, not because it's deprecated
 * but because what we are doing here is translate the access path
 * of a PHP file to a fully qualified classname.
 * And the way the PSR-0 works is not compatible with that, example :
 *  - /path/to/project/lib/vendor/namespace/package/class/name.php
 *
 * Could be interpreted into both :
 *  - \namespace\package\Class\Name
 *  - \namespace\package\Class_Name
 *
 *  @todo Check if autoload-dev is loaded.
 */
final class ComposerClassFinder implements ClassFinder
{

    /**
     * @var ClassLoader The composer autoloader object.
     */
    private $loader;

    /**
     * @var array The fully qualified names of the project classes.
     */
    private $classes;

    /**
     * @var string The PCRE pattern use to filter PHP files.
     */
    private $phpFilePattern;

    /**
     * @var LoggerInterface The logger to use.
     */
    private $logger;

    /**
     * Initialize the class finder.
     *
     * @param LoggerInterface $logger                    The logger to use.
     * @param string          $autoloadPath              The path to the autoload file generated by composer.
     * @param string          $phpFilePattern (optional) The pattern used to filter PHP files, in PCRE syntax.
     *
     * @throws \RuntimeException If the autoload file path is invalid or if
     * the file is not readable.
     */
    public function __construct(LoggerInterface $logger, $autoloadPath, $phpFilePattern = '/.+\.php[3-57]?$/i')
    {
        Assertion::string($autoloadPath);
        Assertion::string($phpFilePattern);

        $autoloadRealPath = realpath($autoloadPath);

        if (!is_readable($autoloadPath)) {
            $message = sprintf('Unable to locate the autoload file at the location "%s"', $autoloadPath);

            $this->logger->critical($message);

            throw new \RuntimeException($message);
        }

        $this->loader         = include $autoloadPath;
        $this->classes        = [];
        $this->phpFilePattern = $phpFilePattern;
    }

    /**
     * Finds the classes used by the project.
     *
     * @param mixed        $pattern The pattern to provide to the matcher.
     * @param ClassMatcher $matcher The object in charge of finding the corresponding classes.
     *
     * @return array The list of all fully qualified class names matching the pattern.
     */
    public function find($pattern, ClassMatcher $matcher)
    {
        Assertion::string($pattern);

        $results = $this->findMatches($pattern, $matcher);

        // If we don't find any matches then we refresh the classes list and try one more time.
        // In case of a file was created since the last refresh
        if (empty($results)) {
            $results = $this->refreshClasses()
                ->findMatches($pattern, $matcher);
        }

        return $results;
    }

    /**
     * Finds the classes matching a pattern.
     *
     * @param mixed        $pattern The pattern to provide to the matcher.
     * @param ClassMatcher $matcher The object in charge of finding the corresponding classes.
     *
     * @return array The list of all fully qualified class names matching the pattern.
     */
    private function findMatches($pattern, classMatcher $matcher)
    {
        return array_filter($this->classes, function ($fullyQualifiedName) use($pattern, $matcher) {
            return $matcher($fullyQualifiedName, $pattern);
        });
    }

    /**
     * Refresh the "classes" attribute by looking for the classes another time.
     * Used to take in account new files.
     * Will be call once for initialization and once each time a class is not found.
     *
     * @return self
     */
    private function refreshClasses()
    {
        $this->classes = array_unique(array_merge(
            get_declared_traits(),
            get_declared_interfaces(),
            get_declared_classes(),
            $this->findForClassmap(),
            $this->findForPsr4()
        ));

        sort($this->classes, SORT_NATURAL | SORT_FLAG_CASE);

        return $this;
    }


    /**
     * Finds the classes defined in the classmap file.
     *
     * @return array The list of all fully qualified class names.
     */
    private function findForClassmap()
    {
        return array_flip(array_map('realpath', $this->loader->getClassMap()));
    }

    /**
     * Finds the classes bases on the SR-4 standard.
     *
     * @return array The list of all fully qualified class names.
     */
    private function findForPsr4()
    {
        $classes = [];
        $replaceDirectorySeparators = DIRECTORY_SEPARATOR !== self::NAMESPACE_SEPARATOR;

        $psr4Directories = array_merge(
            $this->loader->getPrefixesPsr4(), // To load the classic PSR-4 definitions
            [ '' => $this->loader->getFallbackDirsPsr4() ] // To load the fallback directories with an empty prefix
        );

        foreach ($psr4Directories as $namespacePrefix => $paths) {
            foreach ($paths as $path) {
                try {
                    $namespacePath = realpath($path);
                    $dir           = new \RecursiveDirectoryIterator($namespacePath);
                    $iterator      = new \RecursiveIteratorIterator($dir);
                    $files         = new \RegexIterator($iterator, $this->phpFilePattern, \RecursiveRegexIterator::MATCH);

                    foreach ($files as $filePath => $file) {
                        // Replace the begining of the path by the namespace's prefix
                        $fullyQualifiedName = str_replace($namespacePath . DIRECTORY_SEPARATOR, $namespacePrefix, $filePath);

                        //Remove the extension :
                        $fullyQualifiedName = substr($fullyQualifiedName, 0, strrpos($fullyQualifiedName, '.'));

                        // Replace all the / by \
                        $classes[$filePath] = $replaceDirectorySeparators
                            ? str_replace(DIRECTORY_SEPARATOR, self::NAMESPACE_SEPARATOR, $fullyQualifiedName)
                            : $fullyQualifiedName;
                    }
                } catch (\UnexpectedValueException $exception) {
                    // If the path does not exist or is not accessible
                    $message = 'Unable to access the path "%s" for the prefix "%s". '
                        . 'You might want to check your autoload configuration.';
                    $this->logger->warning(sprintf($message, realpath($path), $namespacePrefix));
                    continue;
                }
            }
        }

        return $classes;
    }

    /**
     * @todo Add PSR-0, there is an idea. Check if its doable or not, worth it or not...
     *
     * Reverse the logic, instead of trying to recreate the fully qualified name (fqn)
     * from the file path, recreate a partial file path from the pattern of the user.
     *          Might became complex with possible regex, wildchar, etc.
     * And use this partial path to find all matching files.
     * Then open the files and read their namespaces and class name (reflection),
     * which will gave us the PSR-0 fqn that we have to propose for completion.
     */

}
