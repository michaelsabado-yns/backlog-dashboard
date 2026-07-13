const formatReportHours = (hours) => {
  const value = Number(hours ?? 0);

  if (!Number.isFinite(value) || value <= 0) {
    return '0h';
  }

  if (Number.isInteger(value)) {
    return `${value}h`;
  }

  return `${value.toFixed(2)}h`;
};

const resolveProjectName = (ticket) => {
  if (ticket.project_name) {
    return ticket.project_name;
  }

  if (ticket.project_key) {
    return ticket.project_key;
  }

  const match = String(ticket.issue_key ?? '').match(/^(.+)-\d+$/);

  return match?.[1] ?? 'Unknown project';
};

const resolveTicketLabel = (ticket) => {
  const issueKey = String(ticket.issue_key ?? '').trim();
  const summary = String(ticket.summary ?? '').trim();

  if (issueKey === '') {
    return summary || 'Unknown ticket';
  }

  if (summary === '' || summary === issueKey) {
    return issueKey;
  }

  if (summary.startsWith(`${issueKey} `) || summary.startsWith(`${issueKey}-`)) {
    return summary;
  }

  return `${issueKey} ${summary}`;
};

/**
 * @param {Array<{
 *   issue_key?: string,
 *   summary?: string,
 *   worked_hours?: number,
 *   project_key?: string,
 *   project_name?: string,
 *   updated_at?: string,
 * }>} tickets
 */
export function buildDailyProgressReport(tickets) {
  if (!Array.isArray(tickets) || tickets.length === 0) {
    return '';
  }

  const grouped = new Map();

  tickets.forEach((ticket) => {
    const projectName = resolveProjectName(ticket);

    if (!grouped.has(projectName)) {
      grouped.set(projectName, []);
    }

    grouped.get(projectName).push(ticket);
  });

  const lines = ['Progress:', ''];
  const projectNames = [...grouped.keys()].sort((a, b) => a.localeCompare(b));

  projectNames.forEach((projectName, projectIndex) => {
    if (projectIndex > 0) {
      lines.push('');
    }

    lines.push(projectName);

    grouped.get(projectName).forEach((ticket) => {
      lines.push(`* ${resolveTicketLabel(ticket)} - ${formatReportHours(ticket.worked_hours)}`);
    });
  });

  return lines.join('\n');
}
