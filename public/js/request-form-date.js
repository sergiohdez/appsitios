(() => {
  function escapeRegExp(value) {
    return value.replace(/[.*+?^{}()|[\]\\]/g, '\\$&');
  }

  function isValid(value, format) {
    if (typeof value !== 'string' || !value || typeof format !== 'string') return false;

    const parts = {};
    let group = 0;
    let pattern = '^';

    for (let index = 0; index < format.length;) {
      const token = format[index];
      if (token === 'Y' || token === 'm' || token === 'd') {
        group += 1;
        parts[token] = group;
        pattern += token === 'Y' ? '(\\d{4})' : '(\\d{2})';
        index += 1;
      } else {
        pattern += escapeRegExp(token);
        index += 1;
      }
    }
    pattern += '$';

    const match = value.match(new RegExp(pattern));
    if (!match || !parts.Y || !parts.m || !parts.d) return false;

    const year = Number(match[parts.Y]);
    const month = Number(match[parts.m]);
    const day = Number(match[parts.d]);
    if (year < 1 || month < 1 || month > 12 || day < 1 || day > 31) return false;

    const date = new Date(0);
    date.setUTCHours(0, 0, 0, 0);
    date.setUTCFullYear(year, month - 1, day);

    return date.getUTCFullYear() === year
      && date.getUTCMonth() === month - 1
      && date.getUTCDate() === day;
  }

  function valueLength(format) {
    return Array.from(format || '').reduce((length, token) => length + (token === 'Y' ? 4 : token === 'm' || token === 'd' ? 2 : 1), 0);
  }

  window.RequestFormDates = { isValid, valueLength };
})();
