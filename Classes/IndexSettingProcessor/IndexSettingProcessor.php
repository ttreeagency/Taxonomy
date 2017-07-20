<?php
namespace Ttree\Taxonomy\IndexSettingProcessor;

use Flowpack\ElasticSearch\Service\IndexSettingProcessorInterface;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Service\Context;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Domain\Service\ContentContextFactory;
use Neos\Utility\Arrays;
use Ttree\Taxonomy\Service\ManagedVocabulary;

final class IndexSettingProcessor implements IndexSettingProcessorInterface
{
    /**
     * @var ContentContextFactory
     * @Flow\Inject
     */
    protected $contextFactory;

    /**
     * @var ManagedVocabulary
     * @Flow\Inject
     */
    protected $managedVocabulary;

    /**
     * @var array
     * @Flow\InjectConfiguration(path="managedVocabulary")
     */
    protected $settings;

    /**
     * @var int
     */
    protected static $priority = 1;

    public static function getPriority()
    {
        return self::$priority;
    }

    public function canProcess(array $settings, $path)
    {
        return true;
    }

    public function process(array $settings, $path, $indexName)
    {
        if ($this->settings['enabled'] !== true) {
            return $settings;
        }

        $path = \explode('.', $path);
        $indexPrefix = array_pop($path);
        if (!isset($this->settings['dimensionsMapping'][$indexPrefix])) {
            return $settings;
        }

        $context = $this->createContentContext($this->settings['dimensionsMapping'][$indexPrefix]);
        $taxonomies = (new FlowQuery([$context->getRootNode()]))->find('[instanceof Ttree.Taxonomy:Document.Taxonomy]')->get();
        /** @var NodeInterface $taxonomy */
        foreach ($taxonomies as $taxonomy) {
            $taxonomySettings = $this->managedVocabulary->build($taxonomy);
            $settings = Arrays::arrayMergeRecursiveOverrule($settings, $taxonomySettings);
        }

        return $settings;
    }

    protected function createContentContext(array $dimensions = array()): ContentContext
    {
        $contextProperties = array(
            'workspaceName' => 'live',
            'invisibleContentShown' => false,
            'inaccessibleContentShown' => false
        );

        if ($dimensions !== array()) {
            $contextProperties['dimensions'] = $dimensions;
            $contextProperties['targetDimensions'] = array_map(function ($dimensionValues) {
                return array_shift($dimensionValues);
            }, $dimensions);
        }

        return $this->contextFactory->create($contextProperties);
    }
}
