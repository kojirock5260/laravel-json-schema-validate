<?php

declare(strict_types=1);

namespace Kojirock5260\JsonSchemaValidate\Observation;

use JsonException;

/**
 * 観測から起こしたスキーマと spec のスキーマを突き合わせ、食い違いを列挙する。
 *
 * 観測側にあって spec に無いものと、spec の宣言が観測と合わないものを対象とする。
 * spec にあって観測されなかった項目は、単に通っていないだけの可能性があるため報告しない。
 */
final class SchemaDiff
{
    /**
     * ボディ全体を指すときに使う名前。
     */
    private const string ROOT = 'body';

    /**
     * 静的メソッドのみを提供するため、インスタンス化を禁止する。
     */
    private function __construct() {}

    /**
     * 観測と spec を突き合わせる。
     *
     * @param  array<string, mixed>  $observed  観測から起こしたスキーマ
     * @param  array<string, mixed>|null  $spec  spec 側のスキーマ。定義が無ければ null
     * @return array<string, list<string>> ドット記法をキーにした食い違いの一覧
     */
    public static function compare(array $observed, ?array $spec): array
    {
        if ($observed === []) {
            return [];
        }

        if ($spec === null) {
            return [self::ROOT => ['Observed but the specification defines no schema.']];
        }

        return self::walk($observed, $spec, self::ROOT);
    }

    /**
     * spec 側のスキーマを比較できる形へ変換する。
     *
     * @param  object|null  $schema  SpecSchema が返したスキーマ
     * @return array<string, mixed>|null 変換されたスキーマ。変換できなければ null
     */
    public static function fromSpec(?object $schema): ?array
    {
        if ($schema === null) {
            return null;
        }

        try {
            $decoded = json_decode(json_encode($schema, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * スキーマを再帰的に突き合わせる。
     *
     * @param  array<string, mixed>  $observed  観測から起こしたスキーマ
     * @param  array<string, mixed>  $spec  spec 側のスキーマ
     * @param  string  $path  現在たどっている位置
     * @return array<string, list<string>> 食い違いの一覧
     */
    private static function walk(array $observed, array $spec, string $path): array
    {
        $differences = self::compareTypes($observed, $spec, $path);
        $differences = array_merge($differences, self::compareRequired($observed, $spec, $path));
        $differences = array_merge($differences, self::compareProperties($observed, $spec, $path));

        return array_merge($differences, self::compareItems($observed, $spec, $path));
    }

    /**
     * 観測された型が spec の宣言に収まっているかを調べる。
     *
     * @param  array<string, mixed>  $observed  観測から起こしたスキーマ
     * @param  array<string, mixed>  $spec  spec 側のスキーマ
     * @param  string  $path  現在たどっている位置
     * @return array<string, list<string>> 食い違いの一覧
     */
    private static function compareTypes(array $observed, array $spec, string $path): array
    {
        $specTypes = self::types($spec);

        if ($specTypes === []) {
            return [];
        }

        $unexpected = array_values(array_diff(self::types($observed), $specTypes));

        if ($unexpected === []) {
            return [];
        }

        return [$path => [sprintf(
            'Observed type %s but the specification allows %s.',
            implode('|', $unexpected),
            implode('|', $specTypes),
        )]];
    }

    /**
     * spec が required としている項目が、常に観測されているかを調べる。
     *
     * @param  array<string, mixed>  $observed  観測から起こしたスキーマ
     * @param  array<string, mixed>  $spec  spec 側のスキーマ
     * @param  string  $path  現在たどっている位置
     * @return array<string, list<string>> 食い違いの一覧
     */
    private static function compareRequired(array $observed, array $spec, string $path): array
    {
        if (! in_array('object', self::types($observed), true)) {
            return [];
        }

        $missing = array_values(array_diff(
            self::stringList($spec['required'] ?? null),
            self::stringList($observed['required'] ?? null),
        ));

        return $missing === [] ? [] : [$path => [sprintf(
            'The specification requires %s but they were not always present.',
            implode(', ', $missing),
        )]];
    }

    /**
     * 観測された項目が spec に記載されているかを調べる。
     *
     * @param  array<string, mixed>  $observed  観測から起こしたスキーマ
     * @param  array<string, mixed>  $spec  spec 側のスキーマ
     * @param  string  $path  現在たどっている位置
     * @return array<string, list<string>> 食い違いの一覧
     */
    private static function compareProperties(array $observed, array $spec, string $path): array
    {
        $specProperties = self::schemaMap($spec['properties'] ?? null);
        $differences = [];

        foreach (self::schemaMap($observed['properties'] ?? null) as $name => $schema) {
            $childPath = $path.'.'.$name;

            if (! isset($specProperties[$name])) {
                $differences[$childPath] = ['Observed but not described in the specification.'];

                continue;
            }

            $differences = array_merge($differences, self::walk($schema, $specProperties[$name], $childPath));
        }

        return $differences;
    }

    /**
     * 配列要素のスキーマを突き合わせる。
     *
     * @param  array<string, mixed>  $observed  観測から起こしたスキーマ
     * @param  array<string, mixed>  $spec  spec 側のスキーマ
     * @param  string  $path  現在たどっている位置
     * @return array<string, list<string>> 食い違いの一覧
     */
    private static function compareItems(array $observed, array $spec, string $path): array
    {
        $observedItems = $observed['items'] ?? null;
        $specItems = $spec['items'] ?? null;

        if (! is_array($observedItems) || ! is_array($specItems)) {
            return [];
        }

        return self::walk($observedItems, $specItems, $path.'[]');
    }

    /**
     * スキーマが宣言する型を一覧で返す。
     *
     * @param  array<string, mixed>  $schema  対象のスキーマ
     * @return list<string> 型名の一覧
     */
    private static function types(array $schema): array
    {
        $type = $schema['type'] ?? null;

        if (is_string($type)) {
            return [$type];
        }

        return is_array($type) ? array_values(array_filter($type, is_string(...))) : [];
    }

    /**
     * 値を文字列の一覧として取り出す。
     *
     * @param  mixed  $value  取り出す対象
     * @return list<string> 文字列の一覧
     */
    private static function stringList(mixed $value): array
    {
        return is_array($value) ? array_values(array_filter($value, is_string(...))) : [];
    }

    /**
     * 値をスキーマの連想配列として取り出す。
     *
     * @param  mixed  $value  取り出す対象
     * @return array<string, array<string, mixed>> スキーマの連想配列
     */
    private static function schemaMap(mixed $value): array
    {
        return is_array($value) ? array_filter($value, is_array(...)) : [];
    }
}
