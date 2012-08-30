<?php
/*******************************************************************************
 * Project: FrontBuilder
 * ============================================================================
 * FrontBuilder is a simple tool developed for execution with php-cli, that 
 * enables developer to automate the optimization of javascript and css files in
 * their project. FrontBuilder uses google-closute-compiler for the optimization
 * of javascript and yui-compressor for css files. FrontBuilder depends on a
 * manifest json file in the root of the front end project to provide 
 * information about the build process.
 * ============================================================================
 * Usage: php front_builder/build /path/manifest.json
 * ============================================================================
 * 
 * Created by:  Alexander Mihaylov
 * Company:     158ltd.com (http://www.158ltd.com)
 * GitHub:      https://github.com/lexmihaylov
 * 
 ******************************************************************************/

/**
 * Usage: FrontBuilder::main($argc, $argv);
 */
class FrontBuilder {
    /**
     * Holds the manifest's filename
     * @var string 
     */
    private $manifest;
    
    /**
     * Holds the configuration data loaded from the manifest
     * @var stdClass
     */
    private $project;
    
    /**
     * Holds the path to the compilator executables
     * @var string 
     */
    private $compilerPath;
    /**
     * Holds the path to the front end application
     * @var string 
     */
    private $appPath;
    
    /**
     * Holds the name of the temp file that contains the javascript that will be
     * optimized
     * @var string 
     */
    private $tmpJsFile;
    
    /**
     * Holds the name of the temp file that contains the css that will be
     * optimized
     * @var string 
     */
    private $tmpCssFile;
    
    /**
     * Indicates if building for debugging purposes
     * @var boolean 
     */
    private $debug = false;

    /**
     * Creates on instance of FrontBuilder
     * @param string(optional) $manifest The path to the manifest file. If a 
     * manifest file is not given then 'manifest.json' will be used.
     */
    public function __construct($manifest = 'manifest.json') {
        $this->manifest = basename($manifest);
        $this->compilerPath = dirname(__FILE__);
        $this->appPath = dirname($manifest);
    }

    /**
     * Creates an instance of FrontBuilder and executes the build() method
     * @param array $argv Argument array
     */
    public static function main($argv) {
        // Compilation routine
        $builder = new FrontBuilder();
        $builder->setArguments($argv);
        $builder->build();
    }

    /**
     * Creates a release directory, copies resources and optimizes css and 
     * javascript files. 
     */
    public function build() {
        // load manifest file
        $this->loadProjectManifest();
        // chanfe working directory
        chdir($this->appPath);
        echo "Note: Working directory changed to: " . getcwd() . "\n\n";
        // optimize css and js
        echo "Building '{$this->project->projectName}' ... \n";
        $this->setTmp();
        $this->createReleaseFolders();
        $this->copyResourses();
        $this->buildJavascript();
        $this->buildStyle();
    }
    
    /**
     * Evaluates arguments passed by the shell
     * @param array $argv 
     */
    private function setArguments($argv) {
        for($i = 1; $i < count($argv); $i++) {
            switch ($argv[$i]) {
                case '-h':
                case '--h':
                case '-help':
                case '--help':
                    echo "Usage:\n" .
                    "  php build [--debug]\n" .
                    "  php ./build [--debug][project manifest]\n" .
                    "  php ./build --help\n\n".
                    "  --help  \tShows this screen.\n".
                    "  --debug \tMerges all the files together and creates the ".
                    "release folder but does not optimizes css and javascript.\n".
                    "  --version  \tPrints the version of FrontBuilder.\n";

                    exit(0);
                case '-v':
                case '--v':
                case '-version':
                case '--version':
                    echo " FrontBuilder v1.0. Release Date: 29/08/2012\n";
                    exit(0);
                case '-d':
                case '--d':
                case '-debug':
                case '--debug':
                    $this->debug = true;
                    break;
                default:
                    $this->manifest = basename($argv[$i]);
                    $this->appPath = dirname($argv[$i]);
            }
        }
    }
    
    /**
     * Set the names of the temporary css and js files 
     */
    private function setTmp() {
        $this->tmpJsFile = ".{$this->project->projectName}.js.tmp";
        $this->tmpCssFile = ".{$this->project->projectName}.css.tmp";
    }

    /**
     * Loads the configuration information from the manifest file and saves it 
     * to $this->project 
     */
    private function loadProjectManifest() {
        if (!file_exists($this->appPath . '/' . $this->manifest)) {
            trigger_error("No project file found in $this->appPath.", E_USER_ERROR);
        }

        $buildConfigurations = utf8_encode(file_get_contents($this->appPath . '/' . $this->manifest));

        $this->project = json_decode($buildConfigurations);

        if (empty($this->project)) {
            trigger_error("There was an error while parsing $this->manifest", E_USER_ERROR);
        }
    }

    /**
     * Creates the release forlder or cleans it up if the folder exists 
     */
    private function createReleaseFolders() {
        if (!is_dir($this->project->releaseFolder)) {
            mkdir($this->project->releaseFolder);
        } else {
            echo "Cleaning {$this->project->releaseFolder} folder" . PHP_EOL;
            system("rm -r {$this->project->releaseFolder}/*");
        }
    }

    /**
     * Copies the resources defined in the manifest file to the realease folder 
     */
    private function copyResourses() {
        foreach ($this->project->resources as $resource) {
            $resoursePath = dirname($resource);
            $directoriesToResource = explode('/', $resoursePath);

            $newResourcePath = $this->project->releaseFolder;
            foreach ($directoriesToResource as $directory) {
                $newResourcePath .= '/' . $directory;
                if (!is_dir($newResourcePath)) {
                    mkdir($newResourcePath);
                }
            }

            if (is_dir($resource)) {
                system("cp -r $resource $newResourcePath");
            } elseif (file_exists($resource)) {
                system("cp $resource $newResourcePath");
            } else {
                trigger_error("Cannot copy resource: $resource", E_USER_ERROR);
            }
        }
    }

    /**
     * Uses google-closure-compiler to optimize javascript files defined in the
     * manifest and saves the output to the release folder
     */
    private function buildJavascript() {
        $javaScript = '';
        $compileParams = implode(' ', $this->project->closureBuildParameters);

        foreach ($this->project->files as $file) {
            if (!file_exists($this->appPath . '/' . $file)) {
                trigger_error("Connot include $this->appPath/$file.", E_USER_ERROR);
            }

            $javaScript .= file_get_contents($this->appPath . '/' . $file) . PHP_EOL;
        }
        
        if(!$this->debug) {
            file_put_contents($this->tmpJsFile, $javaScript);

            echo 'Build: java -jar ./compiler.jar '
            . $compileParams . ' --js ./' . $this->tmpJsFile
            . ' --js_output_file '
            . $this->project->releaseFolder . '/' . $this->project->build->jsPath . PHP_EOL;

            echo system('java -jar ' . $this->compilerPath . '/compiler.jar '
                    . $compileParams . ' --js ./' . $this->tmpJsFile
                    . ' --js_output_file '
                    . $this->project->releaseFolder . '/' . $this->project->build->jsPath);

            unlink($this->tmpJsFile);
        } else {
            echo "Debug: " .$this->project->releaseFolder . '/' . $this->project->build->jsPath . PHP_EOL;
            file_put_contents($this->project->releaseFolder . '/' . $this->project->build->jsPath, $javaScript);
        }
    }

    /**
     * Uses yui-compressor to optimize css files defined in the
     * manifest and saves the output to the release folder
     */
    private function buildStyle() {
        $style = '';
        $compressParams = implode(' ', $this->project->compressorBuildParameters);

        foreach ($this->project->styles as $file) {
            if (!file_exists($this->appPath . '/' . $file)) {
                trigger_error("Connot include $this->appPath/$file.", E_USER_ERROR);
            }
            $style .= file_get_contents($this->appPath . '/' . $file) . PHP_EOL;
        }
        
        if(!$this->debug) {
            file_put_contents($this->tmpCssFile, $style);
        
            echo "Build: java -jar ./compressor.jar $compressParams $this->tmpCssFile " .
            "-o {$this->project->releaseFolder}/{$this->project->build->cssPath}" .
            PHP_EOL;

            echo system("java -jar $this->compilerPath/compressor.jar $compressParams " .
                    "--type css ./$this->tmpCssFile " .
                    "-o {$this->project->releaseFolder}/{$this->project->build->cssPath}");
                    
            unlink($this->tmpCssFile);
        } else {
            echo "Debug: {$this->project->releaseFolder}/{$this->project->build->cssPath}" . PHP_EOL;
            file_put_contents("{$this->project->releaseFolder}/{$this->project->build->cssPath}", $style);
        }

        
    }

}

// Initialize FrontBuilder
FrontBuilder::main($argv);
?>
