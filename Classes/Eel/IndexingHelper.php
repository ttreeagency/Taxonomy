<?php
namespace Ttree\Taxonomy\Eel;

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;

class IndexingHelper implements ProtectedContextAwareInterface
{
    public function buildSuggestions(NodeInterface $node): array
    {
        if (!$node->getNodeType()->isOfType('Ttree.Taxonomy:Mixin.Taggable')) {
            return [];
        }
        return [];
    }

    public function expandTerm($nodes, string $propertyName)
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeProperties = [];
        foreach ($nodes as $node) {
            $rootline = $this->termRootline($node);
            $nodeProperties[] = $this->termRootlineToPath($rootline, $propertyName);
        }

        return $nodeProperties;
    }

    public function expandTermSuggest($nodes, string $propertyName)
    {
        if (!is_array($nodes) && !$nodes instanceof \Traversable) {
            return [];
        }
        $nodeProperties = [];
        foreach ($nodes as $node) {
            $rootline = $this->termRootline($node);
            $nodeProperties[] = [
                'input' => array_map(function (NodeInterface $node) use ($propertyName) {
                    return $node->getProperty($propertyName);
                }, $rootline),
                'output' => $this->termRootlineToPath($rootline, $propertyName),
                'payload' => [
                    'level' => 1,
                    'path' => array_map(function (NodeInterface $node) use ($propertyName) {
                        return [
                            'identifier' => $node->getIdentifier(),
                            $propertyName => $node->getProperty($propertyName),
                        ];
                    }, $rootline)
                ],
            ];
        }

        return $nodeProperties;
    }

    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }

    protected function termRootlineToPath(array $rootline, string $propertyName): string
    {
        $path = [];
        foreach ($rootline as $node) {
            $path[] = $node->getProperty($propertyName);
        }
        return \implode(' / ', $path);
    }

    protected function termRootline(NodeInterface $node): array
    {
        /** @var array $rootline */
        $rootline = (new FlowQuery([$node]))->parents('[instanceof Ttree.Taxonomy:Mixin.Term]')->get();
        \array_unshift($rootline, $node);
        return \array_reverse($rootline);
    }
}
