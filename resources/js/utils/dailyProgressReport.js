import { extractCommonTaskGroup, formatCommonTaskLines } from './commonTaskProgressReport';

/**
 * Format worked hours for progress report output (e.g. 1.5h or 2h or 0h).
 *
 * @param {number|string|null} hours
 * @returns {string}
 */
export const formatReportHours = (hours) => {
  const value = Number(hours ?? 0);

  if (!Number.isFinite(value) || value <= 0) {
    return '0h';
  }

  if (Number.isInteger(value)) {
    return `${value}h`;
  }

  return `${value.toFixed(2)}h`;
};

/**
 * Resolve project name or key for a ticket.
 *
 * @param {object} ticket
 * @returns {string}
 */
export const resolveProjectName = (ticket) => {
  if (ticket.project_name) {
    return ticket.project_name;
  }

  if (ticket.project_key) {
    return ticket.project_key;
  }

  const match = String(ticket.issue_key ?? '').match(/^(.+)-\d+$/);

  return match?.[1] ?? 'Unknown project';
};

/**
 * Resolve formatted ticket label with issue key, issue type tag, and summary.
 *
 * @param {object} ticket
 * @returns {string}
 */
export const resolveTicketLabel = (ticket) => {
  const issueKey = String(ticket.issue_key ?? '').trim();
  const summary = String(ticket.summary ?? '').trim();
  const issueType = String(ticket.issue_type ?? '').trim();

  let typeTag = issueType ? `[${issueType}]` : '';

  if (typeTag && summary.toLowerCase().includes(typeTag.toLowerCase())) {
    typeTag = '';
  } else if (typeTag && summary.startsWith('[')) {
    const match = summary.match(/^\[([^\]]+)\]/);
    if (match) {
      const existingTag = match[1].toLowerCase();
      const typeLower = issueType.toLowerCase();
      if (existingTag === typeLower || existingTag.includes(typeLower) || typeLower.includes(existingTag)) {
        typeTag = '';
      }
    }
  }

  const formattedTypeTag = typeTag ? ` ${typeTag}` : '';

  if (issueKey === '') {
    return summary || 'Unknown ticket';
  }

  if (summary === '' || summary === issueKey) {
    return `${issueKey}${formattedTypeTag}`;
  }

  if (summary.startsWith(`${issueKey} `) || summary.startsWith(`${issueKey}-`)) {
    return `${summary}${formattedTypeTag}`;
  }

  return `${issueKey}${formattedTypeTag} ${summary}`;
};

/**
 * @param {Array<{
 *   issue_key?: string,
 *   summary?: string,
 *   worked_hours?: number,
 *   project_key?: string,
 *   project_name?: string,
 *   updated_at?: string,
 *   issue_type?: string,
 *   created_user_id?: number|string,
 *   custom_fields?: Array<any>,
 * }>} tickets
 * @param {{
 *   trackedUserId?: number|string,
 * }} options
 */
export function buildDailyProgressReport(tickets, options = {}) {
  if (!Array.isArray(tickets) || tickets.length === 0) {
    return '';
  }

  const grouped = new Map();

  tickets.forEach((ticket) => {
    const rawProjectName = resolveProjectName(ticket);
    const projectName = String(rawProjectName).charAt(0).toUpperCase() + String(rawProjectName).slice(1).toLowerCase();

    if (!grouped.has(projectName)) {
      grouped.set(projectName, []);
    }

    grouped.get(projectName).push(ticket);
  });

  const lines = ['Progress:', ''];
  const projectNames = [...grouped.keys()].sort((a, b) => a.localeCompare(b));
  const trackedUserId = options.trackedUserId ?? null;

  projectNames.forEach((projectName, projectIndex) => {
    lines.push(projectName);

    const projectTickets = grouped.get(projectName);
    const group = extractCommonTaskGroup(projectTickets, trackedUserId);

    group.directChangesTickets.forEach((ticket) => {
      lines.push(`* ${resolveTicketLabel(ticket)} - ${formatReportHours(ticket.worked_hours)}`);
    });

    const commonTaskLines = formatCommonTaskLines(group, formatReportHours, resolveTicketLabel);
    lines.push(...commonTaskLines);
  });

  return lines.join('\n');
}
