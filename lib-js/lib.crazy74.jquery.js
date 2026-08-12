(function (window, $, crazy74) {
	'use strict';
	if (!$)
		return;

	$.getGUID = function () {
		return crazy74.uuid.get('v4');
	};

	$.fn.SmartTime = function (dt, options = {}) {
		const defaults = {
			interval: 10000,
			maxAgoSec: 86400
		};

		const opts = (window.crazy74 && window.crazy74.array)
						? crazy74.array.merge(true, {}, defaults, options)
						: Object.assign({}, defaults, options);

		return this.each(function () {
			var $el = $(this);

			if (typeof dt !== 'undefined') {
				$el.data('smartTimeRaw', dt);
				$el.data('smartTimeOptions', opts);
			}

			var rawDt = $el.data('smartTimeRaw');
			if (!rawDt)
				return;

			var currentOpts = $el.data('smartTimeOptions') || opts;

			var existingTimer = $el.data('smartTimeTimer');
			if (existingTimer) {
				clearInterval(existingTimer);
				$el.removeData('smartTimeTimer');
			}

			var stopTimer = function () {
				var t = $el.data('smartTimeTimer');
				if (t) {
					clearInterval(t);
					$el.removeData('smartTimeTimer');
				}
			};

			var update = function () {
				if (window.crazy74 && window.crazy74.date) {
					var d = crazy74.date.parseDate(rawDt);
					if (!d) {
						stopTimer();
						return;
					}

					var diffSec = Math.floor((new Date().getTime() - d.getTime()) / 1000);

					if (diffSec < 0 || diffSec >= currentOpts.maxAgoSec) {
						$el.text(crazy74.date.fmtSmart(d, currentOpts));
						$el.attr('title', crazy74.date.formatLocale(d, currentOpts));
						stopTimer();
						return;
					}

					$el.text(crazy74.date.fmtAgo(d));
					$el.attr('title', crazy74.date.formatLocale(d, currentOpts));
				} else {
					var d = rawDt instanceof Date ? rawDt : new Date(rawDt);
					$el.text(d.toLocaleString());
				}
			};

			update();

			if (window.crazy74 && window.crazy74.date) {
				var dParsed = crazy74.date.parseDate(rawDt);
				if (dParsed) {
					var diff = Math.floor((new Date().getTime() - dParsed.getTime()) / 1000);
					if (diff >= 0 && diff < currentOpts.maxAgoSec) {
						$el.data('smartTimeTimer', setInterval(update, currentOpts.interval));
					}
				}
			} else {
				$el.data('smartTimeTimer', setInterval(update, currentOpts.interval));
			}
		});
	};

	$.maxZIndex = $.fn.maxZIndex = function (opt) {
		const def = {inc: 10, group: '*'};
		const options = $.extend({}, def, opt);
		let zmax = 0;

		$(options.group).each(function () {
			// Игнорируем "auto" и невалидные значения, приводя NaN к 0
			const zIndex = parseInt($(this).css('z-index'), 10);
			const cur = Number.isNaN(zIndex) ? 0 : zIndex;

			if (cur > zmax) {
				zmax = cur;
			}
		});

		if (!this.jquery) {
			return zmax;
		}

		return this.each(function () {
			zmax += options.inc;
			$(this).css('z-index', zmax);
		});
	};


	$.fn.exists = function () {
		return this.length > 0;
	};

	$.fn.destroy = function () {
		return this.each(function () {
			$(this).off();
			$(this).removeData().empty().remove();
		});
	};

	$.fn.outerHTML = function () {
		return $(this).clone().wrap('<div></div>').parent().html();
	};

	$.fn.FormData = function () {
		var object = {};
		$.each(this.serializeArray(), function (index, param) {
			if (object[param.name] !== undefined) {
				if (!Array.isArray(object[param.name])) {
					object[param.name] = [object[param.name]];
				}
				object[param.name].push(param.value || '');
			} else {
				object[param.name] = param.value || '';
			}
		});
		return object;
	};

	$.isEmpty = function (val) {
		if (val === null || val === undefined) return true;
		if (typeof val === 'string' || Array.isArray(val)) return val.length === 0;
		if (typeof val === 'object') return Object.keys(val).length === 0;
		return false;
	};

})(window, window.jQuery, window.crazy74);