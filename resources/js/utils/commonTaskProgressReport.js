/**
 * Check if a ticket is a common task based on its summary.
 *
 * @param {object} ticket
 * @returns {boolean}
 */
export const isCommonTask = (ticket) => {
  return String(ticket.summary ?? '').toLowerCase().includes('common task');
};

/**
 * Check whether a user ID or user object matches a given user ID.
 *
 * @param {any} val
 * @param {string|number} userId
 * @returns {boolean}
 */
const matchesUser = (val, userId) => {
  if (!val || !userId) {
    return false;
  }

  if (typeof val === 'number' || typeof val === 'string') {
    return String(val).toLowerCase() === String(userId).toLowerCase();
  }

  if (typeof val === 'object') {
    if (val.id && String(val.id) === String(userId)) {
      return true;
    }
    if (val.user_id && String(val.user_id).toLowerCase() === String(userId).toLowerCase()) {
      return true;
    }
    if (val.name && String(val.name).toLowerCase() === String(userId).toLowerCase()) {
      return true;
    }
  }

  return false;
};

/**
 * Check if the given ticket was reviewed by the specified user.
 *
 * @param {object} ticket
 * @param {string|number} userId
 * @returns {boolean}
 */
export const isUserReviewer = (ticket, userId) => {
  if (ticket.is_code_review === true) {
    return true;
  }
  
  // Fallback for tickets loaded from older cache
  if (!ticket || !userId || !Array.isArray(ticket.custom_fields)) {
    return false;
  }

  return ticket.custom_fields.some((field) => {
    const name = String(field?.name ?? '').trim().toLowerCase();

    if (name !== 'reviewer' && name !== 'code reviewer' && name !== 'レビュアー' && name !== 'レビュワ') {
      return false;
    }

    const value = field.value;

    if (!value) {
      return false;
    }

    if (Array.isArray(value)) {
      return value.some((v) => matchesUser(v, userId));
    }

    return matchesUser(value, userId);
  });
};

/**
 * Check if the given ticket was created by the specified user.
 *
 * @param {object} ticket
 * @param {string|number} userId
 * @returns {boolean}
 */
export const isUserCreator = (ticket, userId) => {
  if (ticket.is_created_ticket === true) {
    return true;
  }
  
  // Fallback for tickets loaded from older cache
  if (!ticket || !userId || ticket.created_user_id === null || ticket.created_user_id === undefined) {
    return false;
  }

  return String(ticket.created_user_id) === String(userId);
};

/**
 * Extract numerical portion of the issue key for sorting.
 *
 * @param {object} ticket
 * @returns {number}
 */
export const getTicketNumber = (ticket) => {
  const issueKey = String(ticket?.issue_key ?? '');
  const match = issueKey.match(/-(\d+)$/);
  return match ? parseInt(match[1], 10) : 0;
};

/**
 * Sort array of tickets by issue key number ascending.
 *
 * @param {Array<object>} tickets
 * @returns {Array<object>}
 */
export const sortByTicketNumber = (tickets) => {
  return [...tickets].sort((a, b) => {
    const numA = getTicketNumber(a);
    const numB = getTicketNumber(b);
    if (numA !== numB) {
      return numA - numB;
    }
    return String(a.issue_key ?? '').localeCompare(String(b.issue_key ?? ''));
  });
};

/**
 * Group tickets into common tasks, code reviews, created tickets, and direct changes.
 *
 * @param {Array<object>} tickets
 * @param {string|number|null} trackedUserId
 * @returns {{
 *   commonTasks: Array<object>,
 *   codeReviewTickets: Array<object>,
 *   createdTickets: Array<object>,
 *   directChangesTickets: Array<object>
 * }}
 */
export const extractCommonTaskGroup = (tickets, trackedUserId) => {
  const commonTasks = [];
  const codeReviewTickets = [];
  const createdTickets = [];
  const directChangesTickets = [];

  tickets.forEach((ticket) => {
    if (isCommonTask(ticket)) {
      // Only include common tasks that belong to the tracked user.
      // If trackedUserId is not set, fall back to including all common tasks.
      const ownedByUser =
        !trackedUserId ||
        (ticket.created_user_id != null &&
          String(ticket.created_user_id) === String(trackedUserId));
      if (ownedByUser) {
        commonTasks.push(ticket);
      }
      // Always skip common tasks from the rest of the categorisation.
      return;
    }

    let isCodeReview = false;
    let isCreated = false;

    if (isUserReviewer(ticket, trackedUserId) && (!ticket.worked_hours || ticket.worked_hours <= 0)) {
      codeReviewTickets.push(ticket);
      isCodeReview = true;
    }

    if (isUserCreator(ticket, trackedUserId) && (!ticket.worked_hours || ticket.worked_hours <= 0)) {
      createdTickets.push(ticket);
      isCreated = true;
    }


    if (!isCodeReview && !isCreated) {
      directChangesTickets.push(ticket);
    }
  });

  return {
    commonTasks: sortByTicketNumber(commonTasks),
    codeReviewTickets: sortByTicketNumber(codeReviewTickets),
    createdTickets: sortByTicketNumber(createdTickets),
    directChangesTickets: sortByTicketNumber(directChangesTickets),
  };
};

/**
 * Format common task lines for progress report.
 *
 * @param {object} group
 * @param {function(number): string} formatReportHours
 * @param {function(object): string} resolveTicketLabel
 * @returns {Array<string>}
 */
export const formatCommonTaskLines = (group, formatReportHours, resolveTicketLabel) => {
  const lines = [];

  const codeReviewTickets = sortByTicketNumber(group.codeReviewTickets ?? []);
  const createdTickets = sortByTicketNumber(group.createdTickets ?? []);

  if (group.commonTasks.length > 0) {
    const lastIndex = group.commonTasks.length - 1;
    group.commonTasks.forEach((ticket, index) => {
      lines.push(`* ${resolveTicketLabel(ticket)} - ${formatReportHours(ticket.worked_hours)}`);

      // Attach sub-items under the LAST (newest) common task only.
      if (index === lastIndex) {
        lines.push('    * Daily Scrum Meeting');
        if (codeReviewTickets.length > 0) {
          lines.push('    * Code Review');
          codeReviewTickets.forEach((t) => {
            lines.push(`      * ${resolveTicketLabel(t)}`);
          });
        }
        if (createdTickets.length > 0) {
          lines.push('    * Create and manage tickets');
          createdTickets.forEach((t) => {
            lines.push(`      * ${resolveTicketLabel(t)}`);
          });
        }
      }
    });
  } else if (codeReviewTickets.length > 0 || createdTickets.length > 0) {
    lines.push(`* Common Tasks`);
    lines.push('    * Daily Scrum Meeting');
    if (codeReviewTickets.length > 0) {
      lines.push('    * Code Review');
      codeReviewTickets.forEach((t) => {
        lines.push(`      * ${resolveTicketLabel(t)}`);
      });
    }
    if (createdTickets.length > 0) {
      lines.push('    * Create and manage tickets');
      createdTickets.forEach((t) => {
        lines.push(`      * ${resolveTicketLabel(t)}`);
      });
    }
  }

  return lines;
};
