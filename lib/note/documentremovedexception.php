<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Note;

/**
 * Документ нельзя перезаписать: он в корзине, в архиве или удалён.
 */
final class DocumentRemovedException extends \RuntimeException
{
}
