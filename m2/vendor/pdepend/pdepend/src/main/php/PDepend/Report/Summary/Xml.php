<?php
/**
 * This file is part of PDepend.
 *
 * PHP Version 5
 *
 * Copyright (c) 2008-2017 Manuel Pichler <mapi@pdepend.org>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Manuel Pichler nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @copyright 2008-2017 Manuel Pichler. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */

namespace PDepend\Report\Summary;

use DOMDocument;
use DOMElement;
use PDepend\Metrics\Analyzer;
use PDepend\Metrics\AnalyzerNodeAware;
use PDepend\Metrics\AnalyzerProjectAware;
use PDepend\Report\CodeAwareGenerator;
use PDepend\Report\FileAwareGenerator;
use PDepend\Report\NoLogOutputException;
use PDepend\Source\AST\AbstractASTArtifact;
use PDepend\Source\AST\ASTArtifactList;
use PDepend\Source\AST\ASTClass;
use PDepend\Source\AST\ASTCompilationUnit;
use PDepend\Source\AST\ASTFunction;
use PDepend\Source\AST\ASTInterface;
use PDepend\Source\AST\ASTMethod;
use PDepend\Source\AST\ASTNamespace;
use PDepend\Source\AST\ASTTrait;
use PDepend\Source\ASTVisitor\AbstractASTVisitor;
use PDepend\Util\Utf8Util;

/**
 * This logger generates a summary xml document with aggregated project, class,
 * method and file metrics.
 *
 * @copyright 2008-2017 Manuel Pichler. All rights reserved.
 * @license http://www.opensource.org/licenses/bsd-license.php BSD License
 */
class Xml extends AbstractASTVisitor implements CodeAwareGenerator, FileAwareGenerator
{
    /**
     * The log output file.
     *
     * @var string
     */
    private $logFile = null;

    /**
     * The raw {@link ASTNamespace} instances.
     *
     * @var ASTArtifactList<ASTNamespace>
     */
    protected $code = null;

    /**
     * Set of all analyzed files.
     *
     * @var ASTCompilationUnit[]
     */
    protected $fileSet = array();

    /**
     * List of all analyzers that implement the node aware interface
     * {@link AnalyzerNodeAware}.
     *
     * @var AnalyzerNodeAware[]
     */
    private $nodeAwareAnalyzers = array();

    /**
     * List of all analyzers that implement the node aware interface
     * {@link AnalyzerProjectAware}.
     *
     * @var AnalyzerProjectAware[]
     */
    private $projectAwareAnalyzers = array();

    /**
     * The internal used xml stack.
     *
     * @var DOMElement[]
     */
    private $xmlStack = array();

    /**
     * Sets the output log file.
     *
     * @param string $logFile The output log file.
     *
     * @return void
     */
    public function setLogFile($logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * Returns an <b>array</b> with accepted analyzer types. These types can be
     * concrete analyzer classes or one of the descriptive analyzer interfaces.
     *
     * @return array<string>
     */
    public function getAcceptedAnalyzers()
    {
        return array(
            'pdepend.analyzer.cyclomatic_complexity',
            'pdepend.analyzer.node_loc',
            'pdepend.analyzer.npath_complexity',
            'pdepend.analyzer.inheritance',
            'pdepend.analyzer.node_count',
            'pdepend.analyzer.hierarchy',
            'pdepend.analyzer.crap_index',
            'pdepend.analyzer.code_rank',
            'pdepend.analyzer.coupling',
            'pdepend.analyzer.class_level',
            'pdepend.analyzer.cohesion',
            'pdepend.analyzer.halstead',
            'pdepend.analyzer.maintainability',
        );
    }

    /**
     * Sets the context code nodes.
     *
     * @param ASTArtifactList<ASTNamespace> $artifacts
     *
     * @return void
     */
    public function setArtifacts(ASTArtifactList $artifacts)
    {
        $this->code = $artifacts;
    }

    /**
     * Adds an analyzer to log. If this logger accepts the given analyzer it
     * with return <b>true</b>, otherwise the return value is <b>false</b>.
     *
     * @param Analyzer $analyzer The analyzer to log.
     *
     * @return bool
     */
    public function log(Analyzer $analyzer)
    {
        $accepted = false;
        if ($analyzer instanceof AnalyzerProjectAware) {
            $this->projectAwareAnalyzers[] = $analyzer;

            $accepted = true;
        }
        if ($analyzer instanceof AnalyzerNodeAware) {
            $this->nodeAwareAnalyzers[] = $analyzer;

            $accepted = true;
        }
        return $accepted;
    }

    /**
     * Closes the logger process and writes the output file.
     *
     * @throws NoLogOutputException If the no log target exists.
     *
     * @return void
     */
    public function close()
    {
        if ($this->logFile === null) {
            throw new NoLogOutputException($this);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');

        $dom->formatOutput = true;

        $metrics = $dom->createElement('metrics');
        $metrics->setAttribute('generated', date('Y-m-d\TH:i:s'));
        $metrics->setAttribute('pdepend', '@package_version@');

        foreach ($this->getProjectMetrics() as $name => $value) {
            $metrics->setAttribute($name, $value);
        }

        array_push($this->xmlStack, $metrics);

        foreach ($this->code as $node) {
            $node->accept($this);
        }

        if (count($this->fileSet) > 0) {
            $filesXml = $dom->createElement('files');
            foreach ($this->fileSet as $file) {
                $fileXml = $dom->createElement('file');
                $fileXml->setAttribute('name', Utf8Util::ensureEncoding($file->getFileName()));

                $this->writeNodeMetrics($fileXml, $file);

                $filesXml->appendChild($fileXml);
            }
            $metrics->insertBefore($filesXml, $metrics->firstChild);
        }

        $dom->appendChild($metrics);

        $buffer = $dom->saveXML();
        file_put_contents($this->logFile, $buffer);
    }

    /**
     * Returns an array with all collected project metrics.
     *
     * @return array<string, mixed>
     *
     * @since  0.9.10
     */
    private function getProjectMetrics()
    {
        $projectMetrics = array();
        foreach ($this->projectAwareAnalyzers as $analyzer) {
            $projectMetrics = array_merge(
                $projectMetrics,
                $analyzer->getProjectMetrics()
            );
        }
        ksort($projectMetrics);

        return $projectMetrics;
    }

    /**
     * Visits a class node.
     *
     * @return void
     */
    public function visitClass(ASTClass $class)
    {
        $this->generateTypeXml($class, 'class');
    }

    /**
     * Visits a trait node.
     *
     * @return void
     */
    public function visitTrait(ASTTrait $trait)
    {
        $this->generateTypeXml($trait, 'trait');
    }

    /**
     * Generates the XML for a class or trait node.
     *
     * @param string $typeIdentifier
     *
     * @return void
     */
    private function generateTypeXml(ASTClass $type, $typeIdentifier)
    {
        if (!$type->isUserDefined()) {
            return;
        }

        $xml = end($this->xmlStack);
        if (!$xml) {
            return;
        }

        $doc = $xml->ownerDocument;

        $typeXml = $doc->createElement($typeIdentifier);
        $typeXml->setAttribute('name', Utf8Util::ensureEncoding($type->getName()));
        $typeXml->setAttribute('fqname', Utf8Util::ensureEncoding($type->getNamespacedName()));
        $typeXml->setAttribute('start', (string)$type->getStartLine());
        $typeXml->setAttribute('end', (string)$type->getEndLine());

        $this->writeNodeMetrics($typeXml, $type);
        $this->writeFileReference($typeXml, $type->getCompilationUnit());

        $xml->appendChild($typeXml);

        array_push($this->xmlStack, $typeXml);

        foreach ($type->getMethods() as $method) {
            $method->accept($this);
        }
        foreach ($type->getProperties() as $property) {
            $property->accept($this);
        }

        array_pop($this->xmlStack);
    }

    /**
     * Visits a function node.
     *
     * @return void
     */
    public function visitFunction(ASTFunction $function)
    {
        $xml = end($this->xmlStack);
        if (!$xml) {
            return;
        }

        $doc = $xml->ownerDocument;

        $functionXml = $doc->createElement('function');
        $functionXml->setAttribute('name', Utf8Util::ensureEncoding($function->getName()));
        $functionXml->setAttribute('start', (string)$function->getStartLine());
        $functionXml->setAttribute('end', (string)$function->getEndLine());

        $this->writeNodeMetrics($functionXml, $function);
        $this->writeFileReference($functionXml, $function->getCompilationUnit());

        $xml->appendChild($functionXml);
    }

    /**
     * Visits a code interface object.
     *
     * @return void
     */
    public function visitInterface(ASTInterface $interface)
    {
        // Empty implementation, because we don't want interface methods.
    }

    /**
     * Visits a method node.
     *
     * @return void
     */
    public function visitMethod(ASTMethod $method)
    {
        $xml = end($this->xmlStack);
        if (!$xml) {
            return;
        }

        $doc = $xml->ownerDocument;

        $methodXml = $doc->createElement('method');
        $methodXml->setAttribute('name', Utf8Util::ensureEncoding($method->getName()));
        $methodXml->setAttribute('start', (string)$method->getStartLine());
        $methodXml->setAttribute('end', (string)$method->getEndLine());

        $this->writeNodeMetrics($methodXml, $method);

        $xml->appendChild($methodXml);
    }

    /**
     * Visits a namespace node.
     *
     * @return void
     */
    public function visitNamespace(ASTNamespace $namespace)
    {
        $xml = end($this->xmlStack);
        if (!$xml) {
            return;
        }

        $doc = $xml->ownerDocument;

        $packageXml = $doc->createElement('package');
        $packageXml->setAttribute('name', Utf8Util::ensureEncoding($namespace->getName()));

        $this->writeNodeMetrics($packageXml, $namespace);

        array_push($this->xmlStack, $packageXml);

        foreach ($namespace->getTypes() as $type) {
            $type->accept($this);
        }
        foreach ($namespace->getFunctions() as $function) {
            $function->accept($this);
        }

        array_pop($this->xmlStack);

        if ($packageXml->firstChild === null) {
            return;
        }

        $xml->appendChild($packageXml);
    }

    /**
     * Aggregates all metrics for the given <b>$node</b> instance and adds them
     * to the <b>\DOMElement</b>
     *
     * @return void
     */
    protected function writeNodeMetrics(DOMElement $xml, AbstractASTArtifact $node)
    {
        $metrics = array();
        foreach ($this->nodeAwareAnalyzers as $analyzer) {
            $metrics = array_merge($metrics, $analyzer->getNodeMetrics($node));
        }

        foreach ($metrics as $name => $value) {
            $xml->setAttribute($name, $value);
        }
    }

    /**
     * Appends a file reference element to the given <b>$xml</b> element.
     *
     * <code>
     *   <class name="\PDepend\Engine">
     *     <file name="PDepend/Engine.php" />
     *   </class>
     * </code>
     *
     * @param DOMElement         $xml             The parent xml element.
     * @param ASTCompilationUnit $compilationUnit The code file instance.
     *
     * @return void
     */
    protected function writeFileReference(DOMElement $xml, ASTCompilationUnit $compilationUnit = null)
    {
        if (in_array($compilationUnit, $this->fileSet, true) === false) {
            $this->fileSet[] = $compilationUnit;
        }

        $fileXml = $xml->ownerDocument->createElement('file');
        $fileXml->setAttribute('name', Utf8Util::ensureEncoding($compilationUnit->getFileName()));

        $xml->appendChild($fileXml);
    }
}
