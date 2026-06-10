<?php
/**
 * AUDIT SCRIPT: PHPDoc mass edit corruption scan
 *
 * Scans 4 dimensions:
 *   A. Constructor vs DI mismatch
 *   B. Route vs method existence
 *   C. Use statement validation
 *   D. Constructor type validation
 *
 * Usage: php audit_phpmass.php
 */

$baseDir = __DIR__ . '/accounting-app';
$srcDir = $baseDir . '/src/Accounting';
$configDir = $baseDir . '/config';
$servicesDir = $configDir . '/services';
$routesDir = $configDir . '/routes';

$issues = [];
$issueId = 0;

function addIssue(string $file, string $desc, string $severity): void {
    global $issues, $issueId;
    $issueId++;
    $issues[] = [
        'id' => $issueId,
        'file' => $file,
        'description' => $desc,
        'severity' => $severity,
    ];
}

// PSR-4-like autoloader so we can use class_exists on Accounting\ namespaces
spl_autoload_register(function (string $class) use ($srcDir) {
    $relative = str_replace('Accounting\\', '', $class);
    $file = $srcDir . '/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Parse a PHP file and return tokenized structure for analysis
 */
function parseFile(string $file): array {
    if (!file_exists($file)) return [];
    $code = file_get_contents($file);
    if ($code === false) return [];
    return [
        'code' => $code,
        'tokens' => token_get_all($code),
    ];
}

/**
 * Extract all 'use' statements from PHP code
 */
function extractUseStatements(string $code): array {
    $uses = [];
    $lines = explode("\n", $code);
    foreach ($lines as $line) {
        if (preg_match('/^\s*use\s+([^;]+)\s*;\s*$/', $line, $m)) {
            $uses[] = trim($m[1]);
        }
    }
    return $uses;
}

/**
 * Extract namespace from PHP code
 */
function extractNamespace(string $code): ?string {
    if (preg_match('/^\s*namespace\s+([^;]+)\s*;/m', $code, $m)) {
        return trim($m[1]);
    }
    return null;
}

/**
 * Find the constructor method signature in a class
 */
function findConstructor(array $parsed): ?array {
    $code = $parsed['code'];
    // Match __construct(...) with param types
    if (preg_match('/function\s+__construct\s*\(([^)]*)\)/s', $code, $m)) {
        $paramsStr = trim($m[1]);
        if ($paramsStr === '') return ['params' => []];
        
        $params = [];
        // Split params carefully (handling nested <>)
        $parts = splitParams($paramsStr);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            
            $param = ['type' => null, 'name' => null, 'nullable' => false];
            
            // Check for nullable ?Type
            if (str_starts_with($part, '?')) {
                $param['nullable'] = true;
                $part = substr($part, 1);
            }
            
            // Match "TypeHint $varName" or "...$varName" or "$varName"
            if (preg_match('/^([^\s$]+)\s+(\$[a-zA-Z_]\w*)\s*(?:=.*)?$/', $part, $pm)) {
                $param['type'] = $pm[1];
                $param['name'] = $pm[2];
            } elseif (preg_match('/^(\$[a-zA-Z_]\w*)\s*(?:=.*)?$/', $part, $pm)) {
                $param['name'] = $pm[1];
            } elseif (preg_match('/^(\.\.\.\s*\$[a-zA-Z_]\w*)/', $part, $pm)) {
                $param['name'] = trim($pm[1]);
                $param['variadic'] = true;
            }
            
            $params[] = $param;
        }
        return ['params' => $params];
    }
    return null;
}

function splitParams(string $s): array {
    $parts = [];
    $depth = 0;
    $current = '';
    $len = strlen($s);
    for ($i = 0; $i < $len; $i++) {
        $c = $s[$i];
        if ($c === '<' || $c === '(' || $c === '[') $depth++;
        elseif ($c === '>' || $c === ')' || $c === ']') $depth--;
        elseif ($c === ',' && $depth === 0) {
            $parts[] = $current;
            $current = '';
            continue;
        }
        $current .= $c;
    }
    if (trim($current) !== '') $parts[] = $current;
    return $parts;
}

/**
 * Compare DI constructor call with actual constructor
 */
function scanDIMismatch(): void {
    global $baseDir, $servicesDir, $srcDir;
    
    // Read all service files
    $serviceFiles = glob($servicesDir . '/*.php');
    $mainServiceFile = $baseDir . '/config/services.php';
    $allFiles = array_merge($serviceFiles, [$mainServiceFile]);
    
    // Collect all "new ClassName(...)" calls in services
    $constructCalls = [];
    
    foreach ($allFiles as $sf) {
        $code = file_get_contents($sf);
        if ($code === false) continue;
        
        // Find all "new ClassName(" calls
        // Pattern: new Accounting\Interfaces\HTTP\...(...) or new SomeController(...)
        if (preg_match_all('/new\s+((?:\\\?\w+(?:\\\|\w+))*)\s*\(([^)]*)\)\s*;/s', $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $className = $match[1];
                $argsStr = $match[2];
                
                // Only analyze controllers (but also services for completeness)
                // Check if it's a controller (contains Controller or Service)
                if (str_contains($className, 'Controller') || str_contains($className, 'Service')) {
                    $args = splitParams($argsStr);
                    $args = array_map('trim', $args);
                    $args = array_filter($args, fn($a) => $a !== '');
                    $constructCalls[] = [
                        'file' => $sf,
                        'line' => findLineNumber($code, $match[0]),
                        'class' => ltrim($className, '\\'),
                        'arg_count' => count($args),
                        'args' => array_values($args),
                    ];
                }
            }
        }
    }
    
    // Now match each call against the actual constructor
    foreach ($constructCalls as $call) {
        $class = $call['class'];
        
        // Normalize: remove leading namespace if it's a short name
        // Map short names to FQCN by checking what use statements are in the file
        $fqcn = resolveFQCN($class, $call['file']);
        
        if (!class_exists($fqcn, false) && !interface_exists($fqcn, false)) {
            // Try to load the file manually
            $filePath = classToPath($fqcn);
            if (file_exists($filePath)) {
                require_once $filePath;
            }
        }
        
        if (!class_exists($fqcn, false)) {
            // Maybe it's a short name with use statement
            addIssue(
                $call['file'],
                "DI instantiates '{$call['class']}' (resolved to '{$fqcn}') but class does not exist. Args passed: " . $call['arg_count'],
                'HIGH'
            );
            continue;
        }
        
        // Get constructor via reflection
        try {
            $ref = new ReflectionClass($fqcn);
        } catch (\Throwable $e) {
            addIssue(
                $call['file'],
                "Cannot reflect '{$fqcn}': " . $e->getMessage(),
                'HIGH'
            );
            continue;
        }
        
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            // No constructor = 0 params expected
            if ($call['arg_count'] > 0) {
                addIssue(
                    $call['file'],
                    "DI passes {$call['arg_count']} arg(s) to '{$fqcn}' but the class has no __construct",
                    'HIGH'
                );
            }
            continue;
        }
        
        $ctorParams = $ctor->getParameters();
        $requiredParams = array_filter($ctorParams, fn($p) => !$p->isOptional() && !$p->isDefaultValueAvailable());
        $totalCtorParams = count($ctorParams);
        
        // Count passed args (excluding variadic)
        if ($call['arg_count'] < count($requiredParams)) {
            addIssue(
                $call['file'],
                "DI passes {$call['arg_count']} arg(s) to '{$fqcn}' but constructor requires " . count($requiredParams) . " required param(s) (total {$totalCtorParams})",
                'HIGH'
            );
        }
        
        // Check specific argument types (if we can match them)
        foreach ($ctorParams as $idx => $param) {
            if ($idx >= $call['arg_count']) break;
            
            $type = $param->getType();
            if ($type === null) continue;
            
            $typeName = $type->getName();
            $passedArg = $call['args'][$idx];
            
            // Extract variable name from the arg (e.g., '$journalService', '$pdo')
            if (preg_match('/^\$([a-zA-Z_]\w*)$/', $passedArg, $pm)) {
                $varName = $pm[1];
                // We can't know the actual type of the variable at runtime here,
                // but we can flag if the variable name doesn't match expectations
                // based on naming conventions
            }
        }
        
        // Check if DI passes arg count that matches exactly (not including defaulted)
        $totalExpected = count($ctorParams);
        $hasVariadic = false;
        foreach ($ctorParams as $p) {
            if ($p->isVariadic()) { $hasVariadic = true; break; }
        }
        
        if (!$hasVariadic && $call['arg_count'] > $totalExpected) {
            addIssue(
                $call['file'],
                "DI passes {$call['arg_count']} arg(s) to '{$fqcn}' but constructor has only {$totalExpected} param(s)",
                'HIGH'
            );
        }
    }
}

function resolveFQCN(string $class, string $fromFile): string {
    // If already fully qualified (starts with Accounting\)
    if (str_starts_with($class, 'Accounting\\') || str_starts_with($class, '\\Accounting\\')) {
        return ltrim($class, '\\');
    }
    
    // Read use statements from the file
    $code = file_get_contents($fromFile);
    $uses = [];
    if (preg_match_all('/^\s*use\s+([^;]+)\s*;/m', $code, $m)) {
        foreach ($m[1] as $u) {
            $parts = explode(' as ', $u);
            $fqcn = trim($parts[0]);
            $alias = isset($parts[1]) ? trim($parts[1]) : basename(str_replace('\\', '/', $fqcn));
            $uses[$alias] = $fqcn;
        }
    }
    
    // Check if the short class name matches any use alias
    $shortName = $class;
    if (str_contains($class, '\\')) {
        // Try to match with use statement
        $classParts = explode('\\', $class);
        $firstPart = $classParts[0];
        
        if (isset($uses[$firstPart])) {
            $rest = array_slice($classParts, 1);
            $fqcn = $uses[$firstPart] . '\\' . implode('\\', $rest);
            // Check if this FQCN resolves to a use with 'as' alias
            foreach ($uses as $alias => $fqcnFull) {
                if ($class === $alias) return $fqcnFull;
            }
            return $fqcn;
        }
        
        // If it has namespace separators but no match, check if first part is the namespace prefix
        if (isset($uses[$firstPart])) {
            return $uses[$firstPart] . '\\' . implode('\\', array_slice($classParts, 1));
        }
    }
    
    // Check direct use alias match
    if (isset($uses[$shortName])) {
        return $uses[$shortName];
    }
    
    // Try the class as-is
    return 'Accounting\\' . $class;
}

function classToPath(string $class): string {
    global $srcDir;
    $relative = str_replace('Accounting\\', '', $class);
    return $srcDir . '/' . str_replace('\\', '/', $relative) . '.php';
}

function findLineNumber(string $code, string $search): int {
    $pos = strpos($code, $search);
    if ($pos === false) return 0;
    return substr_count(substr($code, 0, $pos), "\n") + 1;
}

/**
 * Scan B: Route vs method existence
 */
function scanRoutesVsMethods(): void {
    global $routesDir, $baseDir;
    
    $routeFiles = glob($routesDir . '/*.php');
    $mainRouteFile = $baseDir . '/config/routes.php';
    $allFiles = array_merge($routeFiles, [$mainRouteFile]);
    
    // Collect all $c['ControllerName']->method() calls
    $routeCalls = [];
    
    foreach ($allFiles as $rf) {
        $code = file_get_contents($rf);
        if ($code === false) continue;
        
        // Pattern: $c['ControllerName']->method(...
        // Also: $c["ControllerName"]->method(... 
        // Also: $intercompanyController->method(...) [variable-based]
        // Primary pattern for container access
        if (preg_match_all('/\$c\[\s*[\'"]([a-zA-Z_]\w*)[\'"]\s*\]\s*->\s*([a-zA-Z_]\w*)\s*\(/s', $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $routeCalls[] = [
                    'file' => $rf,
                    'controller_key' => $m[1],
                    'method' => $m[2],
                ];
            }
        }
        
        // Handle $someVar->method() where $someVar was assigned from $c['Key']
        // First collect variable assignments
        if (preg_match_all('/\$(\w+)\s*=\s*\$c\[\s*[\'"]([a-zA-Z_]\w*)[\'"]\s*\];/s', $code, $varMappings, PREG_SET_ORDER)) {
            foreach ($varMappings as $vm) {
                $varName = $vm[1];
                $controllerKey = $vm[2];
                // Now find calls to $varName->method()
                $varPattern = '/\$' . preg_quote($varName, '/') . '\s*->\s*([a-zA-Z_]\w*)\s*\(/s';
                if (preg_match_all($varPattern, $code, $methodMatches, PREG_SET_ORDER)) {
                    foreach ($methodMatches as $mm) {
                        $routeCalls[] = [
                            'file' => $rf,
                            'controller_key' => $controllerKey,
                            'method' => $mm[1],
                        ];
                    }
                }
            }
        }
    }
    
    // Map container keys to FQCNs from config/services.php and config/services/40_controllers.php
    $containerMap = buildContainerMap();
    
    foreach ($routeCalls as $call) {
        $key = $call['controller_key'];
        $method = $call['method'];
        
        if (!isset($containerMap[$key])) {
            addIssue(
                $call['file'],
                "Route calls '\$c['{$key}']->{$method}()' but '{$key}' is not registered in DI container",
                'HIGH'
            );
            continue;
        }
        
        $fqcn = $containerMap[$key];
        
        if (!class_exists($fqcn, false)) {
            $path = classToPath($fqcn);
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        if (!class_exists($fqcn, false)) {
            addIssue(
                $call['file'],
                "Route calls '\$c['{$key}']->{$method}()' but class '{$fqcn}' does not exist",
                'HIGH'
            );
            continue;
        }
        
        try {
            $ref = new ReflectionClass($fqcn);
        } catch (\Throwable $e) {
            addIssue(
                $call['file'],
                "Cannot reflect '{$fqcn}' for route method '{$method}': " . $e->getMessage(),
                'HIGH'
            );
            continue;
        }
        
        if (!$ref->hasMethod($method)) {
            // Check for methods that might be inherited
            $parent = $ref->getParentClass();
            $found = false;
            while ($parent) {
                if ($parent->hasMethod($method)) {
                    $found = true;
                    break;
                }
                $parent = $parent->getParentClass();
            }
            
            if (!$found) {
                addIssue(
                    $call['file'],
                    "Route calls '\$c['{$key}']->{$method}()' but method '{$method}' does not exist on class '{$fqcn}'",
                    'HIGH'
                );
            }
        }
    }
}

function buildContainerMap(): array {
    global $baseDir, $servicesDir;
    
    $map = [];
    $serviceFiles = glob($servicesDir . '/*.php');
    $mainServiceFile = $baseDir . '/config/services.php';
    $allFiles = array_merge([$mainServiceFile], $serviceFiles);
    
    // Read all service files, collect variable → FQCN mappings
    // Pattern: $variable = new ClassName(...);
    
    foreach ($allFiles as $sf) {
        $code = file_get_contents($sf);
        if ($code === false) continue;
        
        // Get use statements for this file
        $uses = extractUseStatements($code);
        $useMap = [];
        foreach ($uses as $u) {
            $parts = explode(' as ', $u);
            $fqcn = trim($parts[0]);
            $alias = isset($parts[1]) ? trim($parts[1]) : basename(str_replace('\\', '/', $fqcn));
            $useMap[$alias] = $fqcn;
        }
        // Also add full class names
        foreach ($uses as $u) {
            $fqcn = trim(explode(' as ', $u)[0]);
            $useMap[$fqcn] = $fqcn;
        }
        
        // Find all variable assignments with new ClassName
        if (preg_match_all('/\$(\w+)\s*=\s*new\s+((?:\\\?[a-zA-Z_]\w*(?:\\\[a-zA-Z_]\w*)*))\s*\(/s', $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $varName = $m[1];
                $classRef = ltrim($m[2], '\\');
                
                // Resolve to FQCN
                $fqcn = resolveShortName($classRef, $useMap);
                
                // Store the variable → class mapping
                $map[$varName] = $fqcn;
            }
        }
        
        // Also find 'as' aliases: `use ... as SomeAlias; $alias = new SomeAlias(...)`
    }
    
    // Now build the container return array mapping from services.php
    // Pattern: 'Key' => $variable,  (entries may span many lines)
    $mainCode = file_get_contents($mainServiceFile);
    // Match all 'Key' => $variable patterns inside the return array
    if (preg_match_all("/return\s*\[(.*?)\]\s*;/s", $mainCode, $returnBlocks)) {
        $returnContent = $returnBlocks[1][0];
        if (preg_match_all("/'([a-zA-Z_]\w*)'\s*=>\s*\\$(\w+)/s", $returnContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $key = $m[1];
                $varName = $m[2];
                if (isset($map[$varName])) {
                    $map[$key] = $map[$varName];
                }
            }
        }
    }
    
    // Also read 40_controllers.php for direct controller variable names
    $controllersFile = $servicesDir . '/40_controllers.php';
    if (file_exists($controllersFile)) {
        $code = file_get_contents($controllersFile);
        $uses = extractUseStatements($code);
        $useMap = [];
        foreach ($uses as $u) {
            $parts = explode(' as ', $u);
            $fqcn = trim($parts[0]);
            $alias = isset($parts[1]) ? trim($parts[1]) : basename(str_replace('\\', '/', $fqcn));
            $useMap[$alias] = $fqcn;
        }
        
        if (preg_match_all('/\$(\w+)\s*=\s*new\s+((?:\\\?[a-zA-Z_]\w*(?:\\\[a-zA-Z_]\w*)*))\s*\(/s', $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $varName = $m[1];
                $classRef = ltrim($m[2], '\\');
                $fqcn = resolveShortName($classRef, $useMap);
                $map[$varName] = $fqcn;
            }
        }
        
        // Also get aliased use statements like `use ... as FixedAssetLifecycleController;`
        if (preg_match_all('/use\s+([^;]+)\s+as\s+(\w+)\s*;/s', $code, $aliasMatches, PREG_SET_ORDER)) {
            foreach ($aliasMatches as $am) {
                $fqcn = trim($am[1]);
                $alias = trim($am[2]);
                $useMap[$alias] = $fqcn;
            }
        }
    }
    
    return $map;
}

function resolveShortName(string $name, array $useMap): string {
    // If already FQCN
    if (str_starts_with($name, 'Accounting\\') || str_starts_with($name, '\\Accounting\\')) {
        return ltrim($name, '\\');
    }
    
    // Direct alias match
    if (isset($useMap[$name])) {
        return $useMap[$name];
    }
    
    // Try with namespace prefix
    $short = basename(str_replace('\\', '/', $name));
    if (isset($useMap[$short])) {
        return $useMap[$short];
    }
    
    // The name might be relative (e.g., interfaces\HTTP\Foo) - try use prefix
    $firstPart = explode('\\', $name)[0];
    if (isset($useMap[$firstPart])) {
        $rest = substr($name, strlen($firstPart));
        return $useMap[$firstPart] . $rest;
    }
    
    // Return as-is under the src root
    return ltrim($name, '\\');
}

/**
 * Scan C: Use statement validation
 */
function scanUseStatements(): void {
    global $srcDir;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $checkedClasses = [];
    
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;
        $path = $file->getRealPath();
        $code = file_get_contents($path);
        if ($code === false) continue;
        
        $uses = extractUseStatements($code);
        $fileNamespace = extractNamespace($code);
        
        foreach ($uses as $use) {
            // Normalize use statement
            $fullClass = $use;
            
            // Skip PHP built-in classes
            if (str_starts_with($fullClass, '\\')) {
                // Check if it's a PHP built-in
                $builtin = ltrim($fullClass, '\\');
                if (class_exists($builtin, false) || interface_exists($builtin, false)) {
                    continue;
                }
                // Try to load
                if (class_exists($builtin, false) || interface_exists($builtin, false) || trait_exists($builtin, false)) {
                    continue;
                }
                $fullClass = $builtin;
            }
            
            // Skip if already checked
            if (isset($checkedClasses[$fullClass])) continue;
            
            // Check PHP internal
            if (class_exists($fullClass, false) || interface_exists($fullClass, false) || trait_exists($fullClass, false)) {
                $checkedClasses[$fullClass] = true;
                continue;
            }
            
            // Check if file exists
            $expectedPath = classToPath($fullClass);
            if (!file_exists($expectedPath)) {
                // Try alternate paths (some interfaces might be in different locations)
                $altPath = $srcDir . '/' . str_replace('\\', '/', $fullClass) . '.php';
                if (!file_exists($altPath)) {
                    // It might be an external dependency (PHP built-in with namespace)
                    $isBuiltin = false;
                    // Check against known PHP classes
                    $shortName = basename(str_replace('\\', '/', $fullClass));
                    if (class_exists('\\' . $shortName, false) || interface_exists('\\' . $shortName, false)) {
                        $isBuiltin = true;
                    }
                    
                    if (!$isBuiltin) {
                        addIssue(
                            $path,
                            "Use statement references '{$fullClass}' but no file found at expected path. Expected: {$expectedPath}",
                            'HIGH'
                        );
                        $checkedClasses[$fullClass] = false;
                    } else {
                        $checkedClasses[$fullClass] = true;
                    }
                } else {
                    $checkedClasses[$fullClass] = true;
                }
                continue;
            }
            
            // File exists, try to verify class declaration inside
            // (Skip actual PHP loading to avoid execution errors)
            $destCode = file_get_contents($expectedPath);
            if ($destCode !== false) {
                if (preg_match('/^\s*namespace\s+([^;]+)\s*;/m', $destCode, $nsMatch)) {
                    $destNs = trim($nsMatch[1]);
                    $shortClassName = basename(str_replace('\\', '/', $fullClass));
                    $expectedDecl = $destNs . '\\' . $shortClassName;
                    
                    if ($expectedDecl !== $fullClass) {
                        addIssue(
                            $path,
                            "Use statement '{$fullClass}' resolves to file {$expectedPath} which declares namespace '{$destNs}', expected FQCN '{$expectedDecl}' vs used '{$fullClass}'",
                            'MEDIUM'
                        );
                    }
                }
            }
            
            $checkedClasses[$fullClass] = true;
        }
    }
}

/**
 * Scan D: Constructor type validation
 */
function scanConstructorTypes(): void {
    global $srcDir;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    $checkedTypes = [];
    
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;
        $path = $file->getRealPath();
        $code = file_get_contents($path);
        if ($code === false) continue;
        
        // Find all constructors in this file
        // Match __construct with typed params
        if (preg_match_all('/function\s+__construct\s*\(([^)]*)\)/s', $code, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $paramsStr = $m[1];
                $params = splitParams($paramsStr);
                
                foreach ($params as $paramStr) {
                    $paramStr = trim($paramStr);
                    if ($paramStr === '') continue;
                    
                    // Extract type hint (before $variable)
                    $normalized = $paramStr;
                    
                    // Handle nullable
                    $nullable = false;
                    if (str_starts_with($normalized, '?')) {
                        $nullable = true;
                        $normalized = substr($normalized, 1);
                    }
                    
                    // Handle mixed, array, callable, iterable, self, parent, etc
                    if (preg_match('/^(?:array|callable|iterable|self|parent|static|bool|int|float|string|void|never|mixed|true|false|null|object)\b/', $normalized)) {
                        continue; // Built-in type, skip
                    }
                    
                    // Extract type: before $variable
                    if (preg_match('/^([a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*)\s+\$/', $normalized, $tm)) {
                        $typeName = $tm[1];
                        
                        // Skip built-in/primitive types
                        $primitives = ['string', 'int', 'float', 'bool', 'array', 'callable', 'iterable', 'void', 'never', 'mixed', 'true', 'false', 'null', 'self', 'parent', 'static'];
                        if (in_array(strtolower($typeName), $primitives)) continue;
                        
                        // Already checked?
                        if (isset($checkedTypes[$typeName])) continue;
                        
                        // Check if the type exists
                        if (class_exists($typeName, false) || interface_exists($typeName, false)) {
                            $checkedTypes[$typeName] = true;
                            continue;
                        }
                        
                        // Try to load the file
                        $typePath = classToPath($typeName);
                        if (!file_exists($typePath)) {
                            // It might be from a use statement
                            $uses = extractUseStatements($code);
                            $resolved = null;
                            foreach ($uses as $use) {
                                $parts = explode(' as ', $use);
                                $fqcn = trim($parts[0]);
                                $alias = isset($parts[1]) ? trim($parts[1]) : basename(str_replace('\\', '/', $fqcn));
                                if ($alias === $typeName) {
                                    $resolved = $fqcn;
                                    break;
                                }
                            }
                            
                            if ($resolved !== null) {
                                $resolvedPath = classToPath($resolved);
                                if (!file_exists($resolvedPath)) {
                                    addIssue(
                                        $path,
                                        "Constructor parameter type '{$typeName}' (resolved to '{$resolved}') not found at '{$resolvedPath}'",
                                        'HIGH'
                                    );
                                    $checkedTypes[$typeName] = false;
                                    continue;
                                }
                            } else {
                                // Check if this is a class in the same namespace
                                $ns = extractNamespace($code);
                                if ($ns) {
                                    $sameNsType = $ns . '\\' . $typeName;
                                    $sameNsPath = classToPath($sameNsType);
                                    if (!file_exists($sameNsPath)) {
                                        // Only flag if we truly can't find it
                                        addIssue(
                                            $path,
                                            "Constructor parameter type '{$typeName}' not found. Checked: use statements, same namespace '{$ns}', and FQCN. Type does not exist in codebase.",
                                            'HIGH'
                                        );
                                    }
                                } else {
                                    addIssue(
                                        $path,
                                        "Constructor parameter type '{$typeName}' not found. No resolution possible.",
                                        'HIGH'
                                    );
                                }
                                $checkedTypes[$typeName] = false;
                                continue;
                            }
                        }
                        
                        // File exists, now check class declaration inside
                        $typeCode = file_get_contents($typePath);
                        if ($typeCode !== false) {
                            // Verify class/interface/trait with this name exists in the file
                            $shortName = basename(str_replace('\\', '/', $typeName));
                            $hasClass = preg_match('/\b(class|interface|trait)\s+' . preg_quote($shortName, '/') . '\b/', $typeCode);
                            if (!$hasClass) {
                                addIssue(
                                    $path,
                                    "Constructor parameter type '{$typeName}' file exists at '{$typePath}' but does not declare '{$shortName}'",
                                    'HIGH'
                                );
                            }
                        }
                        
                        $checkedTypes[$typeName] = true;
                    }
                }
            }
        }
    }
}

// ====== RUN ALL SCANS ======

echo "Starting comprehensive audit...\n\n";

echo "=== SCAN A: Constructor vs DI mismatch ===\n";
scanDIMismatch();
echo "  Done.\n\n";

echo "=== SCAN B: Route vs method existence ===\n";
scanRoutesVsMethods();
echo "  Done.\n\n";

echo "=== SCAN C: Use statement validation ===\n";
scanUseStatements();
echo "  Done.\n\n";

echo "=== SCAN D: Constructor type validation ===\n";
scanConstructorTypes();
echo "  Done.\n\n";

// ====== OUTPUT REPORT ======

echo "========================================\n";
echo "         COMPREHENSIVE AUDIT REPORT        \n";
echo "========================================\n\n";
echo "Total issues found: " . count($issues) . "\n\n";

if (count($issues) === 0) {
    echo "  ✓ No issues found. Codebase is clean.\n";
    exit(0);
}

// Group by severity
$severityCounts = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
foreach ($issues as $iss) {
    $severityCounts[$iss['severity']]++;
}

echo "Severity breakdown:\n";
echo "  HIGH:   {$severityCounts['HIGH']}\n";
echo "  MEDIUM: {$severityCounts['MEDIUM']}\n";
echo "  LOW:    {$severityCounts['LOW']}\n\n";

// Group by file
$byFile = [];
foreach ($issues as $iss) {
    $file = $iss['file'];
    $relativeFile = str_replace('/home/projects/BookWise/accounting-app/', '', $file);
    $byFile[$relativeFile][] = $iss;
}
ksort($byFile);

echo "--- Issues by file ---\n";
foreach ($byFile as $file => $fileIssues) {
    echo "\n## FILE: {$file} ##\n";
    foreach ($fileIssues as $iss) {
        echo "  [{$iss['severity']}] {$iss['description']}\n";
    }
}

echo "\n\n--- Full issue listing ---\n";
echo "ID | FILE | SEVERITY | DESCRIPTION\n";
echo str_repeat('-', 120) . "\n";
foreach ($issues as $iss) {
    $relativeFile = str_replace('/home/projects/BookWise/accounting-app/', '', $iss['file']);
    echo sprintf("#%-4d | %-70s | %-6s | %s\n",
        $iss['id'],
        substr($relativeFile, 0, 70),
        $iss['severity'],
        $iss['description']
    );
}

echo "\n\nDone. Total issues: " . count($issues) . "\n";
