<?php
namespace Ttree\Taxonomy\Service;

use Cocur\Slugify\Slugify;
use Flowpack\ElasticSearch\ContentRepositoryAdaptor\LoggerInterface;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
final class ManagedVocabulary
{
    /**
     * @var LoggerInterface
     * @Flow\Inject
     */
    protected $loggerInterface;

    public function build(NodeInterface $parentNode): array
    {
        $name = $parentNode->getProperty('uriPathSegment');

        $autoPhraseSettings = [];
        $vocabularySettings = [];

        $slug = new Slugify([
            'separator' => '_'
        ]);

        $processTerm = function(NodeInterface $term) use ($slug, &$processTerm, &$autoPhraseSettings, &$vocabularySettings) {
            $parents = (new FlowQuery([$term]))->parentsUntil('[instanceof Ttree.Taxonomy:Document.Taxonomy]')->get();

            $title = trim($term->getProperty('title'));
            if ($title === '') {
                return;
            }

            $path = [$title];
            if (count($parents) > 0) {
                /** @var NodeInterface $parentTerm */
                foreach ($parents as $parentTerm) {
                    $path[] = $parentTerm->getProperty('title');
                }
            }
            $termSlug = $slug->slugify($title);

            $autoPhraseSettings[] = $title . ' => ' . $termSlug;

            if (count($parents) > 0) {
                $autophrasingParents = [$termSlug];
                /** @var NodeInterface $parentTerm */
                foreach ($parents as $parentTerm) {
                    $autophrasingParents[] = $slug->slugify(trim($parentTerm->getProperty('title')));
                }
                $synonymsList = \implode(', ', \array_filter($autophrasingParents));
                if (\count(\array_filter($autophrasingParents)) !== count($autophrasingParents)) {
                    $this->loggerInterface->log(\vsprintf('Some empty parents title for the term "%s" (%s)', [$title, $synonymsList]), \LOG_ERR);
                }
                $vocabularySettings[] = $termSlug . ' => ' . $synonymsList;
            }

            $childrens = (new FlowQuery([$term]))->children('[instanceof Ttree.Taxonomy:Document.Term]')->get();
            \array_map($processTerm, $childrens);
        };

        $terms = (new FlowQuery([$parentNode]))->children('[instanceof Ttree.Taxonomy:Document.Term]')->get();
        \array_map($processTerm, $terms);

        $autoPhraseFilter = \strtolower($name) . '_autophrase_syn';
        $vocabularyFilter = \strtolower($name) . '_vocabulary_syn';
        $vocabularyAnalysis = \strtolower($name) . '_taxonomy_text';

        if ($autoPhraseSettings === [] && $vocabularySettings === []) {
            $this->loggerInterface->log('Auto phrasing and vocabulary skipped', \LOG_NOTICE);
            return [];
        }

        $filter = [];
        if ($autoPhraseSettings !== []) {
            $filter[$autoPhraseFilter] = [
                'type' => 'synonym',
                'synonyms' => $autoPhraseSettings
            ];
        }
        if ($vocabularySettings !== []) {
            $filter[$vocabularyFilter] = [
                'type' => 'synonym',
                'synonyms' => $vocabularySettings
            ];
        }

        return [
            'analysis' => [
                'filter' => $filter,
                'analyzer' => [
                    $vocabularyAnalysis => [
                        'tokenizer' => 'standard',
                        'filter' => \array_keys($filter)
                    ]
                ]
            ]
        ];
    }
}
