<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Header\HeaderInterface;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\UnstructuredHeader;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\RawMessage;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\SerializerAwareInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Normalize Mime message classes.
 *
 * It forces the use of a PropertyNormalizer instance for normalization
 * of all data objects composing a Message.
 *
 * Emails using resources for any parts are not serializable.
 */
final class MimeMessageNormalizer implements NormalizerInterface, DenormalizerInterface, SerializerAwareInterface
{
    private NormalizerInterface&DenormalizerInterface $serializer;
    private array $headerClassMap;
    private \ReflectionProperty $headersProperty;

    public function __construct(private readonly PropertyNormalizer $normalizer)
    {
        $this->headerClassMap = (new \ReflectionClassConstant(Headers::class, 'HEADER_CLASS_MAP'))->getValue();
        $this->headersProperty = new \ReflectionProperty(Headers::class, 'headers');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            Message::class => true,
            Headers::class => true,
            HeaderInterface::class => true,
            Address::class => true,
            AbstractPart::class => true,
        ];
    }

    public function setSerializer(SerializerInterface $serializer): void
    {
        if (!$serializer instanceof NormalizerInterface || !$serializer instanceof DenormalizerInterface) {
            throw new LogicException(\sprintf('The passed serializer should implement both NormalizerInterface and DenormalizerInterface, "%s" given.', get_debug_type($serializer)));
        }
        $this->serializer = $serializer;
        $this->normalizer->setSerializer($serializer);
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        if ($data instanceof Headers) {
            $ret = [];
            foreach ($this->headersProperty->getValue($data) as $name => $header) {
                $ret[$name] = $this->serializer->normalize($header, $format, $context);
            }

            return $ret;
        }

        $ret = $this->normalizer->normalize($data, $format, $context);

        if ($data instanceof AbstractPart) {
            $ret['class'] = $data::class;
            unset($ret['seekable'], $ret['cid'], $ret['handle']);
        }

        if ($data instanceof RawMessage && \array_key_exists('message', $ret) && null === $ret['message']) {
            unset($ret['message']);
        }

        return $ret;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        if (Headers::class === $type) {
            $ret = [];
            foreach ($data as $headers) {
                foreach ($headers as $header) {
                    $ret[] = $this->serializer->denormalize($header, $this->headerClassMap[strtolower($header['name'])] ?? UnstructuredHeader::class, $format, $context);
                }
            }

            return new Headers(...$ret);
        }

        if (AbstractPart::class === $type) {
            $type = $data['class'];
            unset($data['class']);
            $data['headers'] = $this->serializer->denormalize($data['headers'], Headers::class, $format, $context);
        }

        return $this->normalizer->denormalize($data, $type, $format, $context);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Message || $data instanceof Headers || $data instanceof HeaderInterface || $data instanceof Address || $data instanceof AbstractPart;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return is_a($type, Message::class, true) || Headers::class === $type || AbstractPart::class === $type;
    }
}
