<?php

namespace Doctrine\Website\RST;

use Gregwar\RST\Builder as BaseBuilder;
use Gregwar\RST\Parser;

/**
 * Override parseAll method and remove file exists check because we have references
 * to files in the rst that don't exist. Remove this after docs get fixed after the switch
 * to the new site.
 */
class Builder extends BaseBuilder
{
    public function recreate()
    {
        return new Builder($this->kernel);
    }

    public function getDocuments() : array
    {
        return $this->documents;
    }

    protected function parseAll()
    {
        $this->display('* Parsing files');
        while ($file = $this->getFileToParse()) {
            $this->display(' -> Parsing '.$file.'...');
            // Process the file
            $rst = $this->getRST($file);

            if (!file_exists($rst)) {
                continue;
            }

            $parser = new Parser(null, $this->kernel);

            $environment = $parser->getEnvironment();
            $environment->setMetas($this->metas);
            $environment->setCurrentFilename($file);
            $environment->setCurrentDirectory($this->directory);
            $environment->setTargetDirectory($this->targetDirectory);
            $environment->setErrorManager($this->errorManager);

            foreach ($this->beforeHooks as $hook) {
                $hook($parser);
            }

            $document = $this->documents[$file] = $parser->parseFile($rst);

            // Calling all the post-process hooks
            foreach ($this->hooks as $hook) {
                $hook($document);
            }

            // Calling the kernel document tweaking
            $this->kernel->postParse($document);

            $dependencies = $document->getEnvironment()->getDependencies();

            if ($dependencies) {
                $this->display(' -> Scanning dependencies of '.$file.'...');
                // Scan the dependencies for this document
                foreach ($dependencies as $dependency) {
                    $this->scan($dependency);
                }
            }

            // Append the meta for this document
            $this->metas->set(
                $file,
                $this->getUrl($document),
                $document->getTitle(),
                $document->getTitles(),
                $document->getTocs(),
                filectime($rst),
                $dependencies
            );
        }
    }
}
