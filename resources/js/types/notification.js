/**
 * @typedef {Object} Notification
 * @property {number} id
 * @property {string} project
 * @property {number|null} project_id
 * @property {string|null} project_key
 * @property {string|null} issue_key
 * @property {string} summary
 * @property {string} sender
 * @property {string} type
 * @property {string|null} content
 * @property {string|null} created_at
 * @property {string|null} issue_status
 * @property {string|null} issue_status_color
 * @property {string|null} backlog_url
 */

/**
 * @typedef {Notification & { isRead: boolean }} NotificationWithReadState
 */

export {};
