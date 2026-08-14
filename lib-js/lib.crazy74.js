(function (global) {
	'use strict';

	function parseBool(value) {
		if (typeof value === 'string') {
			const str = value.trim().toLowerCase();
			if (['true', 'yes', '1', 'да'].includes(str))
				return true;
			if (['false', 'no', '0', 'нет'].includes(str))
				return false;
		}
		return ['boolean', 'number'].includes(typeof value) ? Boolean(value) : false;
	}

	const ArrayVarManager = function () {};

	ArrayVarManager.prototype.get = function (target, key, defaultValue = null) {
		if (typeof target !== 'object' || target === null) {
			return defaultValue;
		}
		if (Array.isArray(key)) {
			if (key.length === 0) {
				return defaultValue;
			}
			const kl = key.slice();
			const kf = kl.shift();
			if (kl.length === 0) {
				return this.get(target, kf, defaultValue);
			} else {
				if (!(kf in target) || target[kf] === undefined || target[kf] === null) {
					return defaultValue;
				}
				return this.get(target[kf], kl, defaultValue);
			}
		}
		if (!(key in target) || target[key] === undefined) {
			return defaultValue;
		}
		return target[key];
	};

	ArrayVarManager.prototype.set = function (target, key, value, operand = '=') {
		if (typeof target !== 'object' || target === null) {
			return;
		}
		if (Array.isArray(key) && key.length === 1) {
			key = key[0];
		}
		if (Array.isArray(key)) {
			const kl = key.slice();
			const kf = kl.shift();
			if (!(kf in target) || typeof target[kf] !== 'object' || target[kf] === null) {
				target[kf] = {};
			}
			this.set(target[kf], kl, value, operand);
		} else {
			const currentVal = this.get(target, key);
			let newVal = value;
			switch (operand) {
				case '+=':
					newVal = currentVal + value;
					break;
				case '-=':
					newVal = currentVal - value;
					break;
				case '*=':
					newVal = currentVal * value;
					break;
				case '/=':
					newVal = currentVal / value;
					break;
				case '.=':
					newVal = String(currentVal || '') + String(value);
					break;
				case '|=':
					newVal = currentVal | value;
					break;
				case '&=':
					newVal = currentVal & value;
					break;
				case '^=':
					newVal = currentVal ^ value;
					break;
				case '%=':
					newVal = currentVal % value;
					break;
				case '=':
				default:
					newVal = value;
					break;
			}
			target[key] = newVal;
		}
	};
	ArrayVarManager.prototype.get_bool = function (target, key, defaultValue = false) {
		const value = this.get(target, key, defaultValue);
		if (typeof value === 'boolean') {
			return value;
		}
		return parseBool(value);
	};

	ArrayVarManager.prototype.getBool = ArrayVarManager.prototype.get_bool;

	ArrayVarManager.prototype.get_array = function (target, key, defaultValue = false) {
		const value = this.get(target, key, defaultValue);
		if (Array.isArray(value) && value.length > 0) {
			return value;
		}
		return defaultValue;
	};

	ArrayVarManager.prototype.getArray = ArrayVarManager.prototype.get_array;

	ArrayVarManager.prototype.merge = function (...args) {
		let deep = false;
		let i = 0;

		if (typeof args[0] === 'boolean') {
			deep = args[0];
			i = 1;
		}

		let target = (args[i] && typeof args[i] === 'object' && !Array.isArray(args[i])) ? args[i] : {};
		i++;

		for (; i < args.length; i++) {
			const obj = args[i];
			if (!obj || typeof obj !== 'object' || Array.isArray(obj))
				continue;

			for (const key in obj) {
				if (Object.prototype.hasOwnProperty.call(obj, key)) {
					const val = obj[key];
					if (deep && val && typeof val === 'object' && !Array.isArray(val)) {
						target[key] = this.merge(true, target[key] && typeof target[key] === 'object' ? target[key] : {}, val);
					} else if (val !== undefined) {
						target[key] = val;
					}
				}
			}
		}

		return target;
	};

	ArrayVarManager.prototype.notEmpty = function (val) {
		if (val === undefined || val === null) return false;
		if (typeof val === 'object') return Object.keys(val).length > 0;
		return true;
	};

	ArrayVarManager.prototype.clone = function (obj) {
		if (Array.isArray(obj)) {
			return obj.map(item => this.clone(item));
		}
		if (obj === null || typeof obj !== 'object') return obj;
		const temp = new obj.constructor();
		for (const key in obj) {
			if (Object.prototype.hasOwnProperty.call(obj, key)) {
				temp[key] = this.clone(obj[key]);
			}
		}
		return temp;
	};

	ArrayVarManager.prototype.assignData = function (target, data, excludes) {
		if (typeof target === 'object' && target !== null && typeof data === 'object' && data !== null) {
			const isExcludesArray = Array.isArray(excludes);
			for (const key in data) {
				if (Object.prototype.hasOwnProperty.call(data, key)) {
					if (!isExcludesArray || excludes.indexOf(key) < 0) {
						target[key] = this.clone(data[key]);
					}
				}
			}
		}
		return target;
	};

	const LangManager = function () {
		this.code = 'ru';
		this.dictionary = {};
	};

	LangManager.prototype.init = function (langCode, initialData) {
		if (langCode)
			this.code = langCode;
		if (initialData && typeof initialData === 'object') {
			this.dictionary = initialData;
		}
		return this;
	};

	LangManager.prototype.Text = function (...args) {
		if (!args.length)
			return '';

		let keyList = [];
		let fallback = null;

		if (Array.isArray(args[0])) {
			keyList = args[0];
			if (args.length > 1) {
				fallback = args[1];
			}
		} else {
			keyList = args;
		}

		if (fallback === null || fallback === undefined) {
			fallback = '[' + keyList.join('::') + ']';
		}

		const result = crazy74.array.get(this.dictionary, keyList, fallback);

		return (result !== undefined && result !== null) ? result : fallback;
	};

	LangManager.prototype.get = LangManager.prototype.Text;

	/* --- NumberManager --- */
	const NumberManager = function () {};
	/**
	 * Склонение существительных по числу
	 * @param {number} num
	 * @param {Array<string>} endings - ['штука', 'штуки', 'штук']
	 * @param {string} nullText
	 */
	NumberManager.prototype.ending = function (num, endings = ['штука', 'штуки', 'штук'], nullText = '') {
		if (num === 0)
			return nullText;

		// Если есть дробная часть — всегда родительный падеж единственного числа ("1.5 штуки")
		if (num % 1 !== 0) {
			return ' ' + endings[1];
		}

		const abs = Math.abs(Math.trunc(num));
		const i100 = abs % 100;
		const i10 = abs % 10;

		let sEnding = endings[2];
		if (i100 >= 11 && i100 <= 19) {
			sEnding = endings[2];
		} else if (i10 === 1) {
			sEnding = endings[0];
		} else if (i10 >= 2 && i10 <= 4) {
			sEnding = endings[1];
		}

		return ' ' + sEnding;
	};

	/**
	 * Форматирование байт (1024)
	 */
	NumberManager.prototype.sizebytes = function (bytes) {
		let val = Number(bytes) || 0;
		const units = ['b', 'Kb', 'Mb', 'Gb', 'Tb'];
		let i = 0;

		while (Math.abs(val) >= 1024 && i < units.length - 1) {
			val /= 1024;
			i++;
		}

		// Автоматический отброс лишних нулей после запятой
		const formatted = Number(val.toFixed(2)).toString().replace('.', ',');
		return formatted + ' ' + units[i];
	};

	/**
	 * Умное форматирование больших чисел и единиц
	 */
	NumberManager.prototype.smartFormat = function (value, unitKey = false, hideOne = false, fixPrecision = 0, maxPrecision = 4) {
		let result = Number(value) || 0;
		let endings = false;
		let postfix = '';

		if (typeof unitKey === 'string') {
			const unitData = crazy74.array.get(crazy74.lang.dictionary, ['numbers', 'units', unitKey]);
			if (Array.isArray(unitData)) {
				endings = unitData;
			}
		}

		const threshold = 10000;
		if (Math.abs(result) >= threshold) {
			result /= 1000;
			postfix = ' ' + crazy74.array.get(crazy74.lang.dictionary, ['numbers', 'postfix', 'kilo'], 'k');
			if (Math.abs(result) >= 1000) {
				result /= 1000;
				postfix = ' ' + crazy74.array.get(crazy74.lang.dictionary, ['numbers', 'postfix', 'mega'], 'M');
				if (Math.abs(result) >= 1000) {
					result /= 1000;
					postfix = ' ' + crazy74.array.get(crazy74.lang.dictionary, ['numbers', 'postfix', 'giga'], 'G');
				}
			}
			if (endings)
				postfix += ' ' + endings[2];
		}

		let formattedNum = '';
		if (fixPrecision > 0) {
			formattedNum = result.toFixed(fixPrecision);
		} else {
			formattedNum = Number(result.toFixed(maxPrecision)).toString();
		}
		formattedNum = formattedNum.replace('.', ',');

		if (hideOne && Number(value) === 1 && endings) {
			return endings[0];
		}

		let unitStr = '';
		if (endings && Math.abs(Number(value)) < threshold) {
			unitStr = this.ending(result, endings);
		} else if (typeof unitKey === 'string' && !endings) {
			const unitText = crazy74.array.get(crazy74.lang.dictionary, ['numbers', 'units', unitKey]);
			unitStr = ' ' + (typeof unitText === 'string' ? unitText : unitKey);
		}

		return formattedNum + postfix + unitStr;
	};
	
	NumberManager.prototype.withEnding = function (number, endings, nullStr = '&nbsp;') {
		const absNumber = Math.abs(Number(number) || 0);
		if (absNumber === 0) {
			return nullStr;
		}
		return `${number} ${this.ending(number, endings)}`;
	};

	/* --- DateManager --- */
	const DateManager = function () {};

	DateManager.prototype.parseDate = function (dt) {
		if (dt instanceof Date)
			return isNaN(dt.getTime()) ? null : dt;
		if (typeof dt === 'number') {
			return new Date(dt < 1e11 ? dt * 1000 : dt);
		}
		if (typeof dt === 'string') {
			const trimmed = dt.trim();
			if (!trimmed || trimmed === 'never')
				return null;
			if (/^\d+$/.test(trimmed)) {
				const num = Number(trimmed);
				return new Date(num < 1e11 ? num * 1000 : num);
			}

			// Нормализуем формат "YYYY-MM-DD HH:mm:ss" в ISO "YYYY-MM-DDTHH:mm:ss"
			const parsed = new Date(trimmed.replace(' ', 'T'));
			return isNaN(parsed.getTime()) ? null : parsed;
		}
		return new Date();
	};

	DateManager.prototype.formatLocale = function (dt, options = {}) {
		if (dt === 'never') {
			return crazy74.lang.get(['date', 'neverdate'], 'никогда');
		}
		const defaults = {
			format: null,
			withTime: true,
			short: false
		};
		let opt = crazy74.array.merge(true, {}, defaults, options);
		const d = this.parseDate(dt);
		if (!d)
			return '';

		if (!opt.format) {
			const fKey = opt.short
				? (opt.withTime ? 'locale_format_short_with_time' : 'locale_format_short')
				: (opt.withTime ? 'locale_format_with_time' : 'locale_format');
			opt.format = crazy74.lang.get(['date', fKey], opt.withTime ? 'j.m.Y H:i' : 'j.m.Y');
		}

		const day = d.getDate();
		const month = d.getMonth() + 1;
		const year = d.getFullYear();
		const hours = d.getHours();
		const minutes = d.getMinutes();
		const seconds = d.getSeconds();

		const pad = (num) => (num < 10 ? '0' + num : String(num));

		let result = opt.format;

		if (result.includes('F') || result.includes('M')) {
			const months = crazy74.lang.get(['date', 'monthsformat'], null);
			let monthName = '';
			if (Array.isArray(months) && months[month - 1]) {
				monthName = months[month - 1];
			} else if (typeof months === 'object' && months !== null && months[month]) {
				monthName = months[month];
			}
			if (monthName) {
				result = result.replace(/F|M/g, monthName);
			}
		}

		result = result.replace(/j/g, String(day));
		result = result.replace(/d/g, pad(day));
		result = result.replace(/m/g, pad(month));
		result = result.replace(/n/g, String(month));
		result = result.replace(/Y/g, String(year));
		result = result.replace(/y/g, String(year).slice(-2));
		result = result.replace(/H/g, pad(hours));
		result = result.replace(/i/g, pad(minutes));
		result = result.replace(/s/g, pad(seconds));

		return result;
	};

	DateManager.prototype.FormatLocale = DateManager.prototype.formatLocale;

	DateManager.prototype.fmtSmart = function (dt, options = {}) {
		if (dt === 'never') {
			return crazy74.lang.get(['date', 'neverdate'], 'никогда');
		}
		const defaults = {
			withTime: true,
			short: false
		};
		let opt = crazy74.array.merge(true, {}, defaults, options);
		const d = this.parseDate(dt);
		if (!d)
			return '';

		const now = new Date();
		const targetDate = new Date(d.getFullYear(), d.getMonth(), d.getDate());
		const todayDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());

		const diffDays = Math.round((targetDate.getTime() - todayDate.getTime()) / (86400 * 1000));

		let dateLabel = null;
		switch (diffDays) {
			case 0:
				dateLabel = crazy74.lang.get(['date', 'today'], 'сегодня');
				break;
			case - 1:
				dateLabel = crazy74.lang.get(['date', 'yesterday'], 'вчера');
				break;
			case - 2:
				dateLabel = crazy74.lang.get(['date', 'before_yesterday'], 'позавчера');
				break;
			case 1:
				dateLabel = crazy74.lang.get(['date', 'tomorrow'], 'завтра');
				break;
			case 2:
				dateLabel = crazy74.lang.get(['date', 'aftertomorrow'], 'послезавтра');
				break;
			default:
				dateLabel = this.formatLocale(d, crazy74.array.merge(true, {}, opt, { withTime: false }));
				break;
		}

		if (opt.withTime) {
			const pad = (num) => (num < 10 ? '0' + num : String(num));
			const tm = pad(d.getHours()) + ':' + pad(d.getMinutes());
			return dateLabel + ' ' + tm;
		}

		return dateLabel;
	};

	DateManager.prototype.fmtAgo = function (dt) {
		if (dt === 'never') {
			return crazy74.lang.get(['date', 'neverdate'], 'никогда');
		}
		const d = this.parseDate(dt);
		if (!d)
			return '';

		const now = new Date();
		const diffSec = Math.floor((now.getTime() - d.getTime()) / 1000);

		if (diffSec < 0) {
			return this.fmtSmart(d);
		}

		if (diffSec < 10) {
			return crazy74.lang.get(['date', 'just_now'], 'только что');
		}

		if (diffSec < 60) {
			const endings = crazy74.lang.get(['date', 'units', 'second'], ['секунду', 'секунды', 'секунд']);
			const ago = crazy74.lang.get(['date', 'ago'], 'назад');
			return diffSec + crazy74.number.ending(diffSec, endings) + ' ' + ago;
		}

		const diffMin = Math.floor(diffSec / 60);
		if (diffMin < 60) {
			const endings = crazy74.lang.get(['date', 'units', 'minute'], ['минуту', 'минуты', 'минут']);
			const ago = crazy74.lang.get(['date', 'ago'], 'назад');
			return diffMin + crazy74.number.ending(diffMin, endings) + ' ' + ago;
		}

		const diffHours = Math.floor(diffMin / 60);
		if (diffHours < 24) {
			const endings = crazy74.lang.get(['date', 'units', 'hour'], ['час', 'часа', 'часов']);
			const ago = crazy74.lang.get(['date', 'ago'], 'назад');
			return diffHours + crazy74.number.ending(diffHours, endings) + ' ' + ago;
		}

		return this.fmtSmart(d);
	};

	DateManager.prototype.timeAgo = DateManager.prototype.fmtAgo;
	
	const StringManager = function () {};
	StringManager.prototype.escapeHtml = function (text) {
		if (typeof text !== 'string') return text;
		return text
						.replace(/&/g, "&amp;")
						.replace(/</g, "&lt;")
						.replace(/>/g, "&gt;")
						.replace(/"/g, "&quot;")
						.replace(/'/g, "&#039;");
	};

	const UUID_Manager = function () {
		const build_v4 = () => {
			return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
				(c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
			);
		};
		const base36 = (length = 16) => {
			return Array.from(crypto.getRandomValues(new Uint8Array(length)))
        .map(b => (b % 36).toString(36))
        .join('');
		};
		this.get = (algorithm = 'v4', ...args) => {
			switch (algorithm) {
				case 'base36':
					return base36(...args);
				default:
					return build_v4();
			}
		};
	};

	const crazy74 = global.crazy74 || {};
	crazy74.lang = new LangManager();
	crazy74.number = new NumberManager();
	crazy74.array = new ArrayVarManager();
	crazy74.date = new DateManager();
	crazy74.string = new StringManager();
	crazy74.uuid = new UUID_Manager();
	crazy74.bool = parseBool;
	global.crazy74 = crazy74;

})(typeof window !== 'undefined' ? window : this);