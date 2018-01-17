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
                'input' => $this->explodedInput($rootline, $propertyName),
                'weight' => count($rootline) * 10,
                'output' => $this->termRootlineToPath($rootline, $propertyName),
                'payload' => [
                    'level' => count($rootline),
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

    public function explodedInput(array $rootline, string $propertyName): array
    {
        $segments = array_map(function (NodeInterface $node) use ($propertyName) {
            return trim($node->getProperty($propertyName));
        }, $rootline);

        $wordList = \implode(' ', $segments);
        $wordList = \str_replace(["'", "`", "-"], ' ', $wordList);
        $wordList = \array_filter(\explode(' ', $wordList));

        foreach ($wordList as $word) {
            if (\mb_strlen($word) <= 3) {
                continue;
            }
            $segments[] = $word;
        }

        return $segments;
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
        $rootline = (new FlowQuery([$node]))->parents('[instanceof Ttree.Taxonomy:Document.Term]')->get();
        \array_unshift($rootline, $node);
        return \array_reverse($rootline);
    }
}
