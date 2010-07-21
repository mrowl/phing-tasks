<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information please see
 * <http://phing.info>.
*/
require_once 'phing/Task.php';

/**
 * Task to minify javascript files.
 *
 * Requires Closure compiler which can be found
 * at http://code.google.com/intl/fr/closure/compiler/
 *
 * @author Scott
 */
class JsCompileTask extends Task
{
    /**
     * path to closure compiler
     *
     * @var string
     */
    protected $compilerPath = '';
    /**
     * the source files
     *
     * @var  FileSet
     */
    protected $filesets    = array();
    /**
     * Whether the build should fail, if
     * errors occured
     *
     * @var boolean
     */
    protected $failonerror = false;
    /**
     * minified javascript file into
     *
     * @var  string
     */
    protected $targetPath = '';

    /**
     *  Nested creator, adds a set of files (nested fileset attribute).
     */
    public function createFileSet()
    {
        $num = array_push($this->filesets, new FileSet());
        return $this->filesets[$num - 1];
    }

    /**
     * Whether the build should fail, if an error occured.
     *
     * @param boolean $value
     */
    public function setFailonerror($value)
    {
        $this->failonerror = $value;
    }

    /**
     * sets the directory where minified javascript files should go
     *
     * @param  string  $targetPath
     */
    public function setTargetPath($targetPath)
    {
        $this->targetPath = $targetPath;
    }

    /**
     * path to closure compiler
     *
     * @param  string  $compilerPath
     */
    public function setCompilerPath($compilerPath)
    {
        $this->compilerPath = $compilerPath;
    }

    /**
     * The init method: Do init steps.
     */
    public function init()
    {
        return true;
    }

    /**
     * The main entry point method.
     */
    public function main()
    {
        if(count($this->filesets) == 0) {
            throw new BuildException("Missing either a nested fileset or attribute 'file' set");
        }
        $commandBase = 'java -jar ' . $this->compilerPath;
        exec($commandBase . ' --helpshort 2>&1', $output);
        if (!preg_match('/"--helpshort" is not a valid option/', implode('', $output))) {
            throw new BuildException('Closure Compiler not found!');
        }
        foreach ($this->filesets as $fs) {
            try {
                $files    = $fs->getDirectoryScanner($this->project)->getIncludedFiles();
                $fullPath = realpath($fs->getDir($this->project));
                if (empty($this->targetPath)) {
                    foreach ($files as $file) {
                        $inputPath = $fullPath . '/' . $file;
                        $command = $commandBase;
                        $command .= ' --js=' . $inputPath;
                        $command .= " --js_output_file={$inputPath}.min";
                        $this->_exec($command);
                        rename("{$inputPath}.min", $inputPath);
                    }
                } else {
                    $command = $commandBase;
                    foreach ($files as $file) {
                        $inputPath = $fullPath . '/' . $file;
                        $command .= ' --js=' . $inputPath;
                    }
                    $target = $this->targetPath;
                    if (file_exists(dirname($target)) === false) {
                        mkdir(dirname($target), 0700, true);
                    }
                    $command .= ' --js_output_file=' . $target;
                    $this->_exec($command);
                }
            } catch (BuildException $be) {
                // directory doesn't exist or is not readable
                if ($this->failonerror) {
                    throw $be;
                } else {
                    $this->log($be->getMessage(), $this->quiet ? Project::MSG_VERBOSE : Project::MSG_WARN);
                }
            }
        }
    }

    private function _exec($command) {
        $this->log('Minifying files with ' . $command);
        exec($command . ' 2>&1', $output, $return);
        if ($return > 0) {
            $out_string = implode("\n", $output);
            $this->log("Error minifiying:\n{$command}\n{$out_string}", Project::MSG_ERR);
            throw new BuildException('error in minification');
        }
    }
}
?>

