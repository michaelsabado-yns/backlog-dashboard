/**
 * Parse actual hours before/after values from Backlog notification content.
 *
 * @param {string|null|undefined} content
 * @returns {{ before: number, after: number }|null}
 */
export function parseActualHoursChange(content) {
  if (!content || typeof content !== 'string') {
    return null;
  }

  const normalized = content.replace(/\r\n/g, '\n');

  const patterns = [
    /actual\s*hours?\s*[:：]?\s*([\d.]+)\s*(?:h|時間)?\s*(?:=>|->|→|⇒|から|to)\s*([\d.]+)/i,
    /実績(?:時間)?\s*[:：]?\s*([\d.]+)\s*(?:h|時間)?\s*(?:=>|->|→|⇒|から|to)\s*([\d.]+)/,
    /([\d.]+)\s*(?:h|時間)?\s*(?:=>|->|→|⇒)\s*([\d.]+)\s*(?:h|時間)?/,
  ];

  for (const pattern of patterns) {
    const match = normalized.match(pattern);

    if (!match) {
      continue;
    }

    const before = Number(match[1]);
    const after = Number(match[2]);

    if (Number.isFinite(before) && Number.isFinite(after) && before !== after) {
      return { before, after };
    }
  }

  return null;
}

/**
 * @param {Array<{ content?: string|null, created_at?: string|null, type?: string, sender?: string }>} notifications
 * @returns {Array<{ before: number, after: number, createdAt: string|null, type: string, sender: string }>}
 */
export function extractHourChangesFromNotifications(notifications) {
  if (!Array.isArray(notifications)) {
    return [];
  }

  return notifications
    .map((notification) => {
      const change = parseActualHoursChange(notification.content);

      if (!change) {
        return null;
      }

      return {
        before: change.before,
        after: change.after,
        createdAt: notification.created_at ?? null,
        type: notification.type ?? 'Notification',
        sender: notification.sender ?? 'Unknown',
      };
    })
    .filter((item) => item !== null);
}
