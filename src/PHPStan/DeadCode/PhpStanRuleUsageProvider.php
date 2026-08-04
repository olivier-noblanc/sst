<?php

declare(strict_types=1);

namespace App\PHPStan\DeadCode;

use PHPStan\Rules\Rule;
use ReflectionMethod;
use ShipMonk\PHPStan\DeadCode\Provider\ReflectionBasedMemberUsageProvider;
use ShipMonk\PHPStan\DeadCode\Provider\VirtualUsageData;

/**
 * Marque les méthodes de l'interface PHPStan\Rules\Rule comme utilisées.
 *
 * Shipmonk dead-code-detector 1.3.x ne reconnaît pas que PHPStan appelle
 * getNodeType() et processNode() via son moteur de règles (par réflexion
 * sur l'interface Rule, pas par appel direct dans le code analysé).
 * Sans ce provider, les 15 règles custom de src/PHPStan/ produisent
 * 30 faux positifs shipmonk.deadMethod.
 *
 * @see https://github.com/shipmonk-rnd/dead-code-detector — PhpStanUsageProvider ne couvre que les constructeurs
 */
final class PhpStanRuleUsageProvider extends ReflectionBasedMemberUsageProvider
{
    private const RULE_INTERFACE = Rule::class;

    /** @var list<string> Méthodes de l'interface Rule appelées par le moteur PHPStan */
    private const RULE_METHODS = ['getNodeType', 'processNode'];

    public function shouldMarkMethodAsUsed(ReflectionMethod $method): ?VirtualUsageData
    {
        $declaringClass = $method->getDeclaringClass();

        if (!$declaringClass->implementsInterface(self::RULE_INTERFACE)) {
            return null;
        }

        if (!in_array($method->getName(), self::RULE_METHODS, true)) {
            return null;
        }

        return VirtualUsageData::withNote(
            'Called by PHPStan rule engine via the ' . self::RULE_INTERFACE . ' interface'
        );
    }
}
