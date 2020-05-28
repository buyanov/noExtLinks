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
        $this->_exec('scripts/phpcs' . ' --standard=PSR2 ' . __DIR__ . '/src');
    }

    public function build()
    {
        $this->taskPack('noextlinks.zip')
            ->addFile('noextlinks.php', 'src/PlgSystemNoExtLinks.php')
            ->add('noextlinks.xml')
            ->add('language')
            ->run();
    }

}