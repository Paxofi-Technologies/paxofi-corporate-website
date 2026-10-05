<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Articles;

use Paxofi\CorporateWebsite\Application\Exception\ValidationFailed;

/**
 * The editable content of one article (D-021), validated. The body is plain
 * text with a small set of formatting marks the website turns into HTML
 * itself: blank lines between paragraphs, "## " headings, "- " list items,
 * **bold** and [link text](https://…). No HTML is accepted.
 */
final readonly class ArticleContent
{
    public const CATEGORIES = ['news' => 'News', 'insight' => 'Insight', 'announcement' => 'Announcement'];
    public const BODY_MAX = 30000;

    public function __construct(
        public string $title,
        public string $category,
        public string $summary,
        public string $body,
        public ?string $authorName,
        public ?string $imageId,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): self
    {
        $errors = [];
        $title = self::line($input['title'] ?? '');
        if (mb_strlen($title) < 5 || mb_strlen($title) > 160) {
            $errors['title'] = 'Enter a title of 5 to 160 characters.';
        }
        $category = is_string($input['category'] ?? null) ? $input['category'] : '';
        if (!isset(self::CATEGORIES[$category])) {
            $errors['category'] = 'Choose News, Insight or Announcement.';
        }
        $summary = self::line($input['summary'] ?? '');
        if (mb_strlen($summary) < 20 || mb_strlen($summary) > 300) {
            $errors['summary'] = 'Write a summary of 20 to 300 characters. It shows on the list and in search results.';
        }
        $body = is_string($input['body'] ?? null) ? trim((string) preg_replace("/[^\\P{Cc}\n\t]/u", '', str_replace(["\r\n", "\r"], "\n", mb_scrub($input['body'], 'UTF-8')))) : '';
        if (mb_strlen($body) < 50 || mb_strlen($body) > self::BODY_MAX) {
            $errors['body'] = 'Write the article (50 to 30,000 characters).';
        } elseif (preg_match('/<\s*[a-z!\/]/i', $body) === 1) {
            $errors['body'] = 'HTML is not accepted. Use the formatting marks shown under the box.';
        } else {
            preg_match_all('/\[[^\]]+\]\(([^)\s]*)\)/', $body, $links);
            foreach ($links[1] as $url) {
                if (!self::isSafeLink($url)) {
                    $errors['body'] = 'Links must start with https://, http://, mailto: or / (a page on this website).';
                    break;
                }
            }
        }
        $author = self::line($input['author_name'] ?? '');
        if (mb_strlen($author) > 80) {
            $errors['author_name'] = 'Keep the author name to 80 characters.';
        }
        $image = is_string($input['image_id'] ?? null) && $input['image_id'] !== '' ? $input['image_id'] : null;
        if ($image !== null && preg_match('/^[0-9a-f-]{36}$/', $image) !== 1) {
            $errors['image_id'] = 'Choose a picture from the media library.';
        }
        if ($errors !== []) {
            throw new ValidationFailed($errors, 'Please correct the highlighted fields.');
        }

        return new self($title, $category, $summary, $body, $author === '' ? null : $author, $image);
    }

    /** @param array<string, mixed> $row live columns, or the stored draft */
    public static function fromStored(array $row): self
    {
        $text = static fn (string $key): string => is_string($row[$key] ?? null) ? $row[$key] : '';
        $category = $text('category');

        return new self(
            $text('title'),
            isset(self::CATEGORIES[$category]) ? $category : 'insight',
            $text('summary'),
            $text('body'),
            ($row['author_name'] ?? null) === null || $row['author_name'] === '' ? null : (string) $row['author_name'],
            ($row['image_id'] ?? null) === null || $row['image_id'] === '' ? null : (string) $row['image_id'],
        );
    }

    public function withImage(?string $imageId): self
    {
        return new self($this->title, $this->category, $this->summary, $this->body, $this->authorName, $imageId);
    }

    /** @return array{title: string, category: string, summary: string, body: string, author_name: ?string, image_id: ?string} */
    public function toArray(): array
    {
        return ['title' => $this->title, 'category' => $this->category, 'summary' => $this->summary, 'body' => $this->body, 'author_name' => $this->authorName, 'image_id' => $this->imageId];
    }

    public static function isSafeLink(string $url): bool
    {
        return preg_match('~^(https?://[^\s"<>]+|mailto:[^\s"<>@]+@[^\s"<>]+|/[^\s"<>]*)$~i', $url) === 1 && !str_starts_with($url, '//');
    }

    private static function line(mixed $value): string
    {
        return is_string($value) ? trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\p{Cf}]/u', ' ', mb_scrub($value, 'UTF-8')))) : '';
    }
}
