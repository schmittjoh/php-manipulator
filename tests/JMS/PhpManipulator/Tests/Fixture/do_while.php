<?php

do {
    $classes[] = $refl;
} while (false !== $refl = $refl->getParentClass());