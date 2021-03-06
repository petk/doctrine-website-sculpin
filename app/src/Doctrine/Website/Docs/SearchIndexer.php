<?php

namespace Doctrine\Website\Docs;

use AlgoliaSearch\Client;
use AlgoliaSearch\Index;
use Doctrine\Website\Projects\Project;
use Doctrine\Website\Projects\ProjectVersion;
use Gregwar\RST\Nodes\Node;
use Gregwar\RST\HTML\Document;
use Gregwar\RST\HTML\Nodes\ParagraphNode;
use Gregwar\RST\HTML\Nodes\TableNode;
use Gregwar\RST\HTML\Nodes\TitleNode;

/**
 * Influenced by Laravel.com website code search indexes that also use Algolia.
 */
class SearchIndexer
{
    const INDEX_NAME = 'pages';

    /** @var Client */
    private $client;

    /** @var RSTBuilder */
    private $rstBuilder;

    public function __construct(Client $client, RSTBuilder $rstBuilder)
    {
        $this->client = $client;
        $this->rstBuilder = $rstBuilder;
    }

    public function initSearchIndex()
    {
        $index = $this->getSearchIndex();

        $index->setSettings([
            'attributesToIndex' => [
                'unordered(projectName)',
                'unordered(h1)',
                'unordered(h2)',
                'unordered(h3)',
                'unordered(h4)',
                'unordered(h5)',
                'unordered(content)',
            ],
            'attributesToIndex' => ['projectName', 'h1', 'h2', 'h3', 'h4', 'h5', 'content'],
            'customRanking' => ['asc(rank)'],
            'ranking' => ['words', 'typo', 'attribute', 'proximity', 'custom'],
            'minWordSizefor1Typo' => 3,
            'minWordSizefor2Typos' => 7,
            'allowTyposOnNumericTokens' => false,
            'minProximity' => 2,
            'ignorePlurals' => true,
            'advancedSyntax' => true,
            'removeWordsIfNoResults' => 'allOptional',
        ]);

        $index->clearIndex();
    }

    public function buildSearchIndexes(
        Project $project,
        ProjectVersion $version)
    {
        $records = [];

        foreach ($this->rstBuilder->getDocuments() as $document) {
            $this->buildDocumentSearchRecords($document, $records, $project, $version);
        }

        $this->getSearchIndex()->addObjects($records);
    }

    private function buildDocumentSearchRecords(
        Document $document,
        array &$records,
        Project $project,
        ProjectVersion $version)
    {
        $environment = $document->getEnvironment();

        $slug = $environment->getUrl();
        $currentLink = $slug;

        $current = [
            'h1' => null,
            'h2' => null,
            'h3' => null,
            'h4' => null,
            'h5' => null,
        ];

        $nodeTypes = [TitleNode::class, ParagraphNode::class];

        $nodes = $document->getNodes(function(Node $node) use ($nodeTypes) {
            return in_array(get_class($node), $nodeTypes);
        });

        foreach ($nodes as $node) {
            $value = $node->getValue();

            if (strpos($value, '{{ SOURCE_FILE') !== false) {
                continue;
            }

            $html = $node->render();

            if ($node instanceof TitleNode) {
                preg_match('/<a id=\"([^\"]*)\">.*<\/a>/iU', $html, $match);

                $currentLink = $slug . '.html#' . $match[1];
            }

            $records[] = $this->getNodeSearchRecord(
                $node, $current, $currentLink, $project, $version
            );
        }
    }

    private function getNodeSearchRecord(
        Node $node,
        array &$current,
        string &$currentLink,
        Project $project,
        ProjectVersion $version) : array
    {
        $level = $node instanceof TitleNode ? $node->getLevel() : false;

        if ($level !== false) {
            $current['h'.$level] = (string) $node->getValue();

            for ($i = ($level + 1); $i <= 5; $i++) {
                $current["h".$i] = null;
            }

            $content = null;
        } else {
            $content = (string) $node->getValue();
        }

        return [
            'objectID' => $version->getSlug().'-'.$currentLink.'-'.md5($node->getValue()),
            'rank' => $this->getRank($node),
            'h1' => $current['h1'],
            'h2'  => $current['h2'],
            'h3'  => $current['h3'],
            'h4'  => $current['h4'],
            'h5'  => $current['h5'],
            'url' => '/projects/'.$project->getDocsSlug().'/en/'.$version->getSlug().'/'.$currentLink,
            'content' => $content ? strip_tags($content) : null,
            'projectName' => $project->getShortName(),
            '_tags' => [
                $version->getSlug(),
                $project->getSlug(),
            ]
        ];
    }

    private function getRank(Node $node) : int
    {
        $ranks = [
            'h1' => 0,
            'h2' => 1,
            'h3' => 2,
            'h4' => 3,
            'h5' => 4,
            'p'  => 5,
        ];

        if ($node instanceof TitleNode) {
            $elementName = 'h'.$node->getLevel();
        } elseif ($node instanceof ParagraphNode) {
            $elementName = 'p';
        }

        return $ranks[$elementName];
    }

    private function getSearchIndex() : Index
    {
        return $this->client->initIndex(self::INDEX_NAME);
    }
}
