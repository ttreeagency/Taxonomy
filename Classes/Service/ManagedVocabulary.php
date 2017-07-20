<?php
namespace Ttree\Taxonomy\Service;

use Cocur\Slugify\Slugify;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
final class ManagedVocabulary
{
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

            $title = $term->getProperty('title');
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
                    $autophrasingParents[] = $slug->slugify($parentTerm->getProperty('title'));
                }
                $vocabularySettings[] = $termSlug . ' => ' . \implode(', ', $autophrasingParents);
            }

            $childrens = (new FlowQuery([$term]))->children('[instanceof Ttree.Taxonomy:Document.Term]')->get();
            \array_map($processTerm, $childrens);
        };

        $terms = (new FlowQuery([$parentNode]))->children('[instanceof Ttree.Taxonomy:Document.Term]')->get();
        \array_map($processTerm, $terms);

        $autoPhraseFilter = \strtolower($name) . '_autophrase_syn';
        $vocabularyFilter = \strtolower($name) . '_vocabulary_syn';
        $vocabularyAnalysis = \strtolower($name) . '_taxonomy_text';

        return [
            'analysis' => [
                'filter' => [
                    $autoPhraseFilter => [
                        'type' => 'synonym',
                        'synonyms' => $autoPhraseSettings
                    ],
                    $vocabularyFilter => [
                        'type' => 'synonym',
                        'synonyms' => $vocabularySettings
                    ],
                ],
                'analyzer' => [
                    $vocabularyAnalysis => [
                        'tokenizer' => 'standard',
                        'filter' => [$autoPhraseFilter, $vocabularyFilter]
                    ]
                ]
            ]
        ];
    }
}
