import MarkdownIt from 'markdown-it';
import { full as emojiPlugin } from 'markdown-it-emoji';
import emojiDefs from 'markdown-it-emoji/lib/data/full.mjs';
import DOMPurify from 'dompurify';

const backlogEmojiDefs = {
  ...emojiDefs,
  bow: '🙇',
  bowing_man: '🙇‍♂️',
  bowing_woman: '🙇‍♀️',
};

const markdown = new MarkdownIt({
  html: false,
  linkify: true,
  breaks: true,
}).use(emojiPlugin, {
  defs: backlogEmojiDefs,
});

/**
 * @param {string} content
 * @returns {string}
 */
export function preprocessBacklogMarkdown(content) {
  return content.replace(
    /!\[([^\]]*)\]\[([^\]]+)\]/g,
    (_match, alt, filename) =>
      `\n\n> 📎 **${alt || 'Image'}:** ${filename} *(open in Backlog to view attachment)*\n\n`,
  );
}

/**
 * @param {string} html
 * @returns {string}
 */
function postprocessHtml(html) {
  return html
    .replace(
      /(^|[\s(>])@([\w.-]+)/g,
      '$1<span class="backlog-mention">@$2</span>',
    )
    .replace(
      /<a href="([^"]+)">/g,
      '<a href="$1" target="_blank" rel="noopener noreferrer">',
    );
}

/**
 * @param {string|null|undefined} content
 * @returns {string}
 */
export function renderBacklogMarkdown(content) {
  if (!content) {
    return '';
  }

  const preprocessed = preprocessBacklogMarkdown(content);
  const rendered = postprocessHtml(markdown.render(preprocessed));

  return DOMPurify.sanitize(rendered, {
    ADD_ATTR: ['target', 'rel', 'class'],
    ADD_TAGS: ['span'],
  });
}

/**
 * Plain-text preview for list views.
 *
 * @param {string|null|undefined} content
 * @param {number} [maxLength=120]
 * @returns {string}
 */
export function previewBacklogMarkdown(content, maxLength = 120) {
  if (!content) {
    return '';
  }

  const plain = content
    .replace(/!\[[^\]]*\]\[[^\]]+\]/g, '[image]')
    .replace(/:[a-z0-9_+-]+:/gi, '')
    .replace(/#{1,6}\s+/g, '')
    .replace(/\*\*([^*]+)\*\*/g, '$1')
    .replace(/\*([^*]+)\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/\s+/g, ' ')
    .trim();

  if (plain.length <= maxLength) {
    return plain;
  }

  return `${plain.slice(0, maxLength).trimEnd()}…`;
}

/**
 * @param {string|null|undefined} content
 */
export function hasCommentContent(content) {
  return typeof content === 'string' && content.trim() !== '';
}

/**
 * Whether a comment is long enough to warrant a show/hide full toggle.
 *
 * @param {string|null|undefined} content
 * @param {number} [maxLength=120]
 */
export function commentNeedsExpand(content, maxLength = 120) {
  if (!hasCommentContent(content)) {
    return false;
  }

  if (content.includes('\n')) {
    return true;
  }

  const plain = content
    .replace(/!\[[^\]]*\]\[[^\]]+\]/g, '[image]')
    .replace(/:[a-z0-9_+-]+:/gi, '')
    .replace(/#{1,6}\s+/g, '')
    .replace(/\*\*([^*]+)\*\*/g, '$1')
    .replace(/\*([^*]+)\*/g, '$1')
    .replace(/`([^`]+)`/g, '$1')
    .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
    .replace(/\s+/g, ' ')
    .trim();

  return plain.length > maxLength;
}
