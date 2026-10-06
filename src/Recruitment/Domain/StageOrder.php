<?php

namespace Src\Recruitment\Domain;

use Src\Shared\Domain\Exceptions\BusinessRuleViolation;

/**
 * The order rules of a company's stages: the default `applied` stage is always first, the active stages follow in any order, and the stages of the
 * terminal types (`rejected`, `withdrawn`) always stay at the end.
 */
final class StageOrder
{
    /**
     * Where a new extra stage goes: just before the first terminal stage, or at the end when there is none.
     *
     * @param  list<Stage>  $ordered
     */
    public static function insertionIndex(array $ordered): int
    {
        foreach ($ordered as $index => $stage) {
            if ($stage->type->isTerminal()) {
                return $index;
            }
        }

        return count($ordered);
    }

    /**
     * The company's stages in the wanted order.
     *
     * @param  list<Stage>  $current
     * @param  list<int>  $wantedIds
     * @return list<Stage>
     *
     * @throws BusinessRuleViolation `invalid-stage-order` with `errors` on `stage_ids`
     */
    public static function arrange(array $current, array $wantedIds): array
    {
        $byId = [];

        foreach ($current as $stage) {
            $byId[(int) $stage->id] = $stage;
        }

        $known = array_keys($byId);
        $given = $wantedIds;
        sort($known);
        sort($given);

        if ($known !== $given) {
            throw self::invalid('The list must name every stage of the company exactly once.');
        }

        $arranged = array_map(static fn (int $id): Stage => $byId[$id], $wantedIds);

        if ($arranged[0]->type !== StageType::Applied) {
            throw self::invalid('The Applied stage must come first.');
        }

        $seenTerminal = false;

        foreach ($arranged as $stage) {
            if ($stage->type->isTerminal()) {
                $seenTerminal = true;
            } elseif ($seenTerminal) {
                throw self::invalid('The Rejected and Withdrawn stages must stay at the end.');
            }
        }

        return $arranged;
    }

    private static function invalid(string $message): BusinessRuleViolation
    {
        return new BusinessRuleViolation($message, 'invalid-stage-order', extensions: ['errors' => ['stage_ids' => [$message]]]);
    }
}
