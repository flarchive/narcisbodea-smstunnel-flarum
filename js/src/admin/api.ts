import app from 'flarum/admin/app';

const ERRORS = [
  'invalid_number',
  'invalid_key',
  'invalid_code',
  'no_key',
  'wait',
  'too_many',
  'too_many_numbers',
  'already_saved',
  'cap',
  'bad_key',
  'refused',
  'unclear',
];

export function call(method: string, path: string, body?: Record<string, unknown>): Promise<any> {
  return app.request({
    method,
    url: app.forum.attribute('apiUrl') + '/smstunnel/admin' + path,
    body,
    // Handled by the caller, in place of the default alert.
    errorHandler: () => {},
  });
}

export function errorText(reason?: string | null) {
  const key = reason && ERRORS.includes(reason) ? reason : 'generic';

  return app.translator.trans('smstunnel-sms.admin.errors.' + key);
}

export function failure(error: any) {
  return errorText(error && error.response && error.response.error);
}

/** Enter in these fields must not submit the settings form around them. */
export function noSubmit(event: KeyboardEvent) {
  if (event.key === 'Enter') event.preventDefault();
}
