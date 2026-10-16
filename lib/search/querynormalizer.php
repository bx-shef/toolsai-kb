<?php declare(strict_types=1);

namespace Shef\ToolsAiKb\Search;

/**
 * Разбор вопроса в слова для полнотекстового поиска `note`.
 *
 * Поиск «Базы знаний» (`Bitrix\Note\Public\Provider\SearchProvider`) ищет
 * документы, где есть КАЖДОЕ слово запроса длиной от трёх букв, с подстановкой
 * `слово*` (`SearchQueryBuilder::prepareBooleanQuery()` ->
 * `Helper::matchAgainstWildcard()`). Живой вопрос «как настроить квоту?»
 * так не находит ничего: «как» — тоже слово, а «квоту» не префикс «квота».
 *
 * Поэтому вопрос сначала чистится: стоп-слова прочь, слова — к основе. Стоп-слова,
 * окончания и сам стеммер перенесены из `Shef\ToolsAi\Kb\Rag` модуля
 * shef.toolsai (1.21.0) без изменения смысла — так прежний поиск по справке и
 * этот разбирают вопрос одинаково; держит tests/querynormalizer_test.php.
 *
 * Без ядра.
 */
final class QueryNormalizer
{
	/** Короче — `note` слово не ищет (SearchLimits::MIN_TOKEN_LENGTH). */
	public const MIN_TERM_LENGTH = 3;

	/** Больше слов — запрос «все слова сразу» почти никогда ничего не находит. */
	public const MAX_TERMS = 6;

	/** Длина запроса, которую берёт `note` (SearchLimits::MAX_QUERY_LENGTH). */
	public const MAX_QUERY_LENGTH = 200;

	private const STOP = 'и в во не что он на я с со как а то все она так его но да ты к у же вы за бы по только ее мне было вот от меня еще нет о из ему теперь когда даже ну вдруг ли если уже или ни быть был него до вас нибудь опять уж вам ведь там потом себя ничего ей может они тут где есть надо ней для мы тебя их чем была сам чтоб без будто чего раз тоже себе под будет ж тогда кто этот того потому этого какой совсем ним здесь этом один почти мой тем чтобы почему зачем какие каких каким нее сейчас были куда зачем всех никогда можно при наконец два об другой хоть после над больше тот через эти нас про всего них какая много разве три эту моя впрочем хорошо свою этой перед иногда лучше чуть том нельзя такой им более всегда конечно всю между это the a an and or of to in is for on with as by be at it this that';

	private const ENDINGS = ['иями', 'ями', 'ами', 'иях', 'ией', 'ием', 'ого', 'его', 'ому', 'ему', 'ыми', 'ими', 'ость', 'ости', 'ств', 'ться', 'тся', 'ешь', 'ете', 'ите', 'ает', 'яет', 'ует', 'ают', 'яют', 'уют', 'ала', 'яла', 'ила', 'ыла', 'ело', 'ать', 'ять', 'ить', 'еть', 'уть', 'ая', 'яя', 'ое', 'ее', 'ые', 'ие', 'ой', 'ей', 'ий', 'ый', 'ую', 'юю', 'ом', 'ем', 'ам', 'ям', 'ах', 'ях', 'ов', 'ев', 'ия', 'ию', 'ии', 'ть', 'ла', 'ло', 'ли', 'а', 'я', 'о', 'е', 'ы', 'и', 'у', 'ю', 'ь', 'й'];

	/** @var array<string, true>|null */
	private static ?array $stop = null;

	/**
	 * Основа слова — как `Rag::stem()`: только кириллица, «ё» -> «е», самое
	 * длинное подходящее окончание из списка, остаток не короче трёх букв.
	 */
	public static function stem(string $w): string
	{
		if(!preg_match('/[а-яё]/u', $w))
		{
			return $w;
		}
		$w = str_replace('ё', 'е', $w);
		$length = mb_strlen($w);
		foreach(self::ENDINGS as $e)
		{
			$el = mb_strlen($e);
			if($length - $el >= 3 && str_ends_with($w, $e))
			{
				return mb_substr($w, 0, $length - $el);
			}
		}

		return $w;
	}

	/**
	 * Слова текста — как `Rag::tokens()`: нижний регистр, без стоп-слов и слов
	 * короче двух букв, к основе; слово с точкой, дефисом или подчёркиванием
	 * даёт и себя, и части.
	 *
	 * @return list<string>
	 */
	public static function tokens(string $text): array
	{
		self::$stop ??= array_fill_keys(explode(' ', self::STOP), true);
		preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}_.\-]*/u', mb_strtolower($text), $m);
		$out = [];
		foreach($m[0] as $raw)
		{
			$w = preg_replace('/[.\-]+$/u', '', $raw) ?? $raw;
			if(mb_strlen($w) < 2 || isset(self::$stop[$w]))
			{
				continue;
			}
			$out[] = static::stem($w);
			if(preg_match('/[._\-]/', $w))
			{
				foreach(preg_split('/[._\-]+/', $w) ?: [] as $part)
				{
					if(mb_strlen($part) >= 2 && !isset(self::$stop[$part]))
					{
						$out[] = static::stem($part);
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Слова для поиска `note`: основы без повторов, от трёх букв, не больше
	 * MAX_TERMS, в порядке появления. Слово с точкой или дефисом целиком не
	 * берётся — `note` всё равно режет его на части, а части уже в списке.
	 *
	 * @return list<string>
	 */
	public static function terms(string $query): array
	{
		$query = mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH);
		$out = [];
		foreach(static::tokens($query) as $token)
		{
			if(mb_strlen($token) < self::MIN_TERM_LENGTH || preg_match('/[._\-]/u', $token) || isset($out[$token]))
			{
				continue;
			}
			$out[$token] = true;
			if(count($out) >= self::MAX_TERMS)
			{
				break;
			}
		}

		// Ключ-число («2024») PHP хранит целым — вернуть строкой
		return array_map('strval', array_keys($out));
	}
}
