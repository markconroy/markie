<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Serializer\Debug\TraceableSerializer;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @final
 */
class SerializerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private const DATA_TEMPLATE = [
        'serialize' => [],
        'deserialize' => [],
        'normalize' => [],
        'denormalize' => [],
        'encode' => [],
        'decode' => [],
    ];

    private array $dataGroupedByName;
    private array $collected = [];

    public function reset(): void
    {
        $this->data = [];
        unset($this->dataGroupedByName);
        $this->collected = [];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        // Everything is collected during the request, and formatted on kernel terminate.
    }

    public function getName(): string
    {
        return 'serializer';
    }

    public function getData(?string $name = null): Data|array
    {
        return null === $name ? $this->data : $this->getDataGroupedByName()[$name];
    }

    public function getHandledCount(?string $name = null): int
    {
        return array_sum(array_map('count', $this->getData($name)));
    }

    public function getTotalTime(): float
    {
        $totalTime = 0;

        foreach ($this->data as $handled) {
            $totalTime += array_sum(array_map(fn (array $el): float => $el['time'], $handled));
        }

        return $totalTime;
    }

    public function getSerializerNames(): array
    {
        return array_keys($this->getDataGroupedByName());
    }

    public function collectSerialize(string $traceId, mixed $data, string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'serialize'],
        );
    }

    public function collectDeserialize(string $traceId, mixed $data, string $type, string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'type', 'context', 'time', 'caller', 'name'),
            ['method' => 'deserialize'],
        );
    }

    public function collectNormalize(string $traceId, mixed $data, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'normalize'],
        );
    }

    public function collectDenormalize(string $traceId, mixed $data, string $type, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'type', 'context', 'time', 'caller', 'name'),
            ['method' => 'denormalize'],
        );
    }

    public function collectEncode(string $traceId, mixed $data, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'encode'],
        );
    }

    public function collectDecode(string $traceId, mixed $data, ?string $format, array $context, float $time, array $caller, string $name): void
    {
        unset($context[TraceableSerializer::DEBUG_TRACE_ID]);

        $this->collected[$traceId] = array_merge(
            $this->collected[$traceId] ?? [],
            compact('data', 'format', 'context', 'time', 'caller', 'name'),
            ['method' => 'decode'],
        );
    }

    public function collectNormalization(string $traceId, string $normalizer, float $time, string $name): void
    {
        $method = 'normalize';

        $this->collected[$traceId]['normalization'][] = compact('normalizer', 'method', 'time', 'name');
    }

    public function collectDenormalization(string $traceId, string $normalizer, float $time, string $name): void
    {
        $method = 'denormalize';

        $this->collected[$traceId]['normalization'][] = compact('normalizer', 'method', 'time', 'name');
    }

    public function collectEncoding(string $traceId, string $encoder, float $time, string $name): void
    {
        $method = 'encode';

        $this->collected[$traceId]['encoding'][] = compact('encoder', 'method', 'time', 'name');
    }

    public function collectDecoding(string $traceId, string $encoder, float $time, string $name): void
    {
        $method = 'decode';

        $this->collected[$traceId]['encoding'][] = compact('encoder', 'method', 'time', 'name');
    }

    public function lateCollect(): void
    {
        $this->data = self::DATA_TEMPLATE;

        foreach ($this->collected as $collected) {
            if (!isset($collected['data'])) {
                continue;
            }

            $data = [
                'data' => $this->cloneVar($collected['data']),
                'dataType' => get_debug_type($collected['data']),
                'type' => $collected['type'] ?? null,
                'format' => $collected['format'],
                'time' => $collected['time'],
                'context' => $this->cloneVar($collected['context']),
                'normalization' => [],
                'encoding' => [],
                'caller' => $collected['caller'] ?? null,
                'name' => $collected['name'],
            ];

            if (isset($collected['normalization'])) {
                $mainNormalization = array_pop($collected['normalization']);

                $data['normalizer'] = ['time' => $mainNormalization['time']] + $this->getMethodLocation($mainNormalization['normalizer'], $mainNormalization['method']);

                foreach ($collected['normalization'] as $normalization) {
                    if (!isset($data['normalization'][$normalization['normalizer']])) {
                        $data['normalization'][$normalization['normalizer']] = ['time' => 0, 'calls' => 0] + $this->getMethodLocation($normalization['normalizer'], $normalization['method']);
                    }

                    ++$data['normalization'][$normalization['normalizer']]['calls'];
                    $data['normalization'][$normalization['normalizer']]['time'] += $normalization['time'];
                }
            }

            if (isset($collected['encoding'])) {
                $mainEncoding = array_pop($collected['encoding']);

                $data['encoder'] = ['time' => $mainEncoding['time']] + $this->getMethodLocation($mainEncoding['encoder'], $mainEncoding['method']);

                foreach ($collected['encoding'] as $encoding) {
                    if (!isset($data['encoding'][$encoding['encoder']])) {
                        $data['encoding'][$encoding['encoder']] = ['time' => 0, 'calls' => 0] + $this->getMethodLocation($encoding['encoder'], $encoding['method']);
                    }

                    ++$data['encoding'][$encoding['encoder']]['calls'];
                    $data['encoding'][$encoding['encoder']]['time'] += $encoding['time'];
                }
            }

            $this->data[$collected['method']][] = $data;
        }
    }

    private function getDataGroupedByName(): array
    {
        if (!isset($this->dataGroupedByName)) {
            $this->dataGroupedByName = [];

            foreach ($this->data as $method => $items) {
                foreach ($items as $item) {
                    $this->dataGroupedByName[$item['name']] ??= self::DATA_TEMPLATE;
                    $this->dataGroupedByName[$item['name']][$method][] = $item;
                }
            }
        }

        return $this->dataGroupedByName;
    }

    private function getMethodLocation(string $class, string $method): array
    {
        $reflection = new \ReflectionClass($class);

        return [
            'class' => $reflection->getShortName(),
            'file' => $reflection->getFileName(),
            'line' => $reflection->getMethod($method)->getStartLine(),
        ];
    }
}
