/**
 * @typedef {Object} Notification
 * @property {number} id
 * @property {string} project
 * @property {string|null} issue_key
 * @property {string} summary
 * @property {string} sender
 * @property {string} type
 * @property {string|null} content
 * @property {string|null} created_at
 * @property {string|null} backlog_url
 */

/**
 * @typedef {Notification & { isRead: boolean }} NotificationWithReadState
 */

export {};
