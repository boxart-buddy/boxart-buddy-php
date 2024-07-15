<?php

namespace App\Provider;

readonly class OrderedListProvider
{
    public function __construct(
        private NamesProvider $namesProvider
    ) {
    }

    public function getOrderedList(array $images): array
    {
        $artwork = [];
        foreach ($images as $image) {
            $romName = basename($image, '.png');
            $artwork[$romName] = $romName;
        }

        $names = $this->namesProvider->getNames();

        $relevant = array_intersect_key($names, $artwork);

        if (0 === count($relevant)) {
            return $relevant;
        }

        asort($relevant);

        return array_flip($relevant);
    }
}
