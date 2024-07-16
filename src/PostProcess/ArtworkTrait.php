<?php

namespace App\PostProcess;

use App\Provider\OrderedListProvider;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;

trait ArtworkTrait
{
    protected function sortArtwork(array $images, LoggerInterface $logger, OrderedListProvider $orderedListProvider): array
    {
        $sortOrder = $orderedListProvider->getOrderedList($images);

        uksort($images, 'strnatcasecmp');

        if (!empty($sortOrder)) {
            // need to sort by the ultimate folder name not the current filename
            usort($images, function ($a, $b) use ($sortOrder, $logger): int {
                // hacks for folders
                if ($this->isFolder($a) || $this->isFolder($b)) {
                    if ($this->isFolder($a) && !$this->isFolder($b)) {
                        return -1;
                    }
                    if (!$this->isFolder($a) && $this->isFolder($b)) {
                        return 1;
                    }

                    return strcmp($a, $b);
                }

                // sortorder is an array ordered correctly with 'image filename ex png
                $platformNameA = basename($a, '.png');
                $platformNameB = basename($b, '.png');

                $positionA = array_search($platformNameA, $sortOrder);
                $positionB = array_search($platformNameB, $sortOrder);

                if (false === $positionA) {
                    $logger->debug(
                        sprintf('Unknown rom in sort_list, rom artwork will appear out of order. To fix this add an entry to names_extra.json for `%s`', $platformNameA)
                    );
                }
                if (false === $positionB) {
                    $logger->debug(
                        sprintf('Unknown rom in sort_list, rom artwork will appear out of order. To fix this add an entry to names_extra.json for `%s`', $platformNameB)
                    );
                }

                return $positionA <=> $positionB;
            });
        }

        return array_values($images);
    }

    private function isFolder(string $path): bool
    {
        return Str::contains($path, 'catalogue/Folder/box');
    }
}
