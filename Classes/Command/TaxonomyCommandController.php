<?php
declare(strict_types=1);

namespace Ttree\Taxonomy\Command;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Controller\CreateContentContextTrait;
use Neos\Flow\Cli\CommandController;
use Neos\Utility\Arrays;
use Ttree\Taxonomy\Service\ManagedVocabulary;

/**
 * @Flow\Scope("singleton")
 */
class TaxonomyCommandController extends CommandController
{
    use CreateContentContextTrait;

    /**
     * @var ManagedVocabulary
     * @Flow\Inject
     */
    protected $managedVocabulary;

    /**
     * @var ContentDimensionCombinator
     * @Flow\Inject
     */
    protected $contentDimensionCombinator;

    /**
     * Show ElasticSearch filters and analyzers
     */
    public function showMappingCommand(string $node)
    {
        foreach ($this->contentDimensionCombinator->getAllAllowedCombinations() as $dimensions) {
            $context = $this->createContentContext('live', $dimensions);
            $siteNode = $context->getNodeByIdentifier($node);
            $taxonomyNodes = (new FlowQuery([$siteNode]))->find('[instanceof Ttree.Taxonomy:Document.Taxonomy]')->get();
            $settings = [];
            /** @var NodeInterface $taxonomyNode */
            foreach ($taxonomyNodes as $taxonomyNode) {
                $this->outputLine();
                $this->outputLine('Show mapping for <b>%s</b> (%s)', [$taxonomyNode->getLabel(), $taxonomyNode->getContextPath()]);
                $settings = $this->managedVocabulary->build($taxonomyNode);
                foreach (Arrays::getValueByPath($settings, 'analysis.analyzer') as $analyzerName => $analyzerConfiguration) {
                    $this->outputLine();
                    $this->outputLine('+ Analyzer  <info>%s</info>', [$analyzerName]);
                    $this->outputLine('  Tokenizer <info>%s</info>', [$analyzerConfiguration['tokenizer']]);
                    $this->outputLine('  Filter    <info>%s</info>', [\implode(', ', $analyzerConfiguration['filter'])]);
                }
                foreach (Arrays::getValueByPath($settings, 'analysis.filter') as $filterName => $filterConfiguration) {
                    $this->outputLine();
                    $this->outputLine('+ Filter    <info>%s</info>', [$analyzerName]);
                    $this->outputLine('  Type      <info>%s</info>', [$filterConfiguration['type']]);
                    if ($filterConfiguration['type'] === 'synonym') {
                        $this->outputLine('  Synonyms  <info>%d</info>', [count($filterConfiguration['synonyms'])]);
                    }
                }
            }
        }
    }
}
