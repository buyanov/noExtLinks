<?php
/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see http://robo.li/
 */
class RoboFile extends \Robo\Tasks
{
    function ls(array $args)
    {
        $this
            ->taskExec('ls')
            ->args($args)
            ->run();
    }

    public function runPhpcs()
    {
        $this->_exec('./scripts/phpcs -n --standard=PSR2 --colors ' . __DIR__ . '/src');
    }

    public function runPhpunit()
    {
        $this->_exec('scripts/phpunit --testdox');
    }

    public function build()
    {
        $this->taskFilesystemStack()
            ->mkdir('dist/noextlinks')
            ->copy('src/PlgSystemNoExtLinks.php', 'dist/noextlinks/noextlinks.php', true)
            ->copy('src/noextlinks.xml', 'dist/noextlinks/noextlinks.xml', true)
            ->copy('src/noextlinks.js', 'dist/noextlinks/noextlinks.js', true)
            ->run();

        $this->_copyDir('language', 'dist/noextlinks/language');
        $this->_copyDir('src/Support', 'dist/noextlinks/Support');
    }

    public function zip()
    {
        $this->_mkdir('dist');

        $this->taskPack('dist/noextlinks.zip')
            ->addFile('noextlinks.php', 'src/PlgSystemNoExtLinks.php')
            ->addFile('noextlinks.xml', 'src/noextlinks.xml')
            ->addFile('noextlinks.js', 'src/noextlinks.js')
            ->addDir('language','language')
            ->addDir('Support', 'src/Support')
            ->run();
    }

}