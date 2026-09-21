<?php

/** PurgeNotArmedException — la sentinelle erase.txt est absente : purge refusée. */

namespace App\Services;

use RuntimeException;

/**
 * Levée lorsque la purge est demandée sans que le fichier sentinelle
 * `erase.txt` soit présent à la racine de l'application.
 */
final class PurgeNotArmedException extends RuntimeException {}
