(function (window, $, crazy74) {
	'use strict';

	if (!$) return;

	var rhorizontal = /^(left|right)$/,
					rvertical = /^(top|bottom)$/;

	function parsePosString(posStr) {
		if (!posStr) {
			return {h: 'left', v: 'top', shiftX: 0, shiftY: 0, hasH: true, hasV: true};
		}

		var parts = $.trim(posStr).split(/\s+/);
		var h = null, v = null;
		var shiftX = 0, shiftY = 0;

		parts.forEach(function (p) {
			var baseName = p.replace(/[+-].*$/, '');
			var matches = p.match(/[+-]\d+/g);

			if (rhorizontal.test(baseName)) {
				h = baseName;
				if (matches)
					shiftX = parseInt(matches[0], 10) || 0;
			} else if (rvertical.test(baseName)) {
				v = baseName;
				if (matches)
					shiftY = parseInt(matches[0], 10) || 0;
			} else if (baseName === 'center' || baseName === 'middle') {
				if (!h) {
					h = 'center';
					if (matches)
						shiftX = parseInt(matches[0], 10) || 0;
				} else if (!v) {
					v = 'center';
					if (matches)
						shiftY = parseInt(matches[0], 10) || 0;
				}
			}
		});

		return {
			h: h || 'left',
			v: v || 'top',
			shiftX: shiftX,
			shiftY: shiftY,
			hasH: h !== null,
			hasV: v !== null
		};
	}

	function getCoordOffset(posToken, size) {
		if (posToken === 'right' || posToken === 'bottom')
			return size;
		if (posToken === 'center' || posToken === 'middle')
			return size / 2;
		return 0;
	}

	function flipToken(token) {
		if (token === 'left')
			return 'right';
		if (token === 'right')
			return 'left';
		if (token === 'top')
			return 'bottom';
		if (token === 'bottom')
			return 'top';
		return token;
	}

	/**
	 * Мульти-объектное позиционирование с контролем коллизий
	 * @param {Array|Object} rules - Одно правило или массив правил
	 * @param {Object} [globalOptions] - Настройки коллизий ({ collision: 'flipfit', within: window })
	 */
	$.fn.csl74Position = function (rules, globalOptions) {
		if (!rules)
			return this;

		var ruleList = Array.isArray(rules) ? rules : [rules];
		if (!ruleList.length)
			return this;

		globalOptions = $.extend({
			collision: 'flipfit', // 'none', 'fit', 'flip', 'flipfit'
			within: window
		}, globalOptions);

		return this.each(function () {
			var $elem = $(this);
			var elemWidth = $elem.outerWidth();
			var elemHeight = $elem.outerHeight();

			var finalPosition = {left: null, top: null};

			// Фиксируем контекст правил, сформировавших итоговые оси
			var lastRuleX = null;
			var lastRuleY = null;

			ruleList.forEach(function (rule) {
				if (!rule || !rule.of)
					return;

				var $target = typeof rule.of === 'string' ? $(rule.of) : $(rule.of);
				if (!$target.length)
					return;

				var targetWidth = $target.outerWidth();
				var targetHeight = $target.outerHeight();
				var targetOffset = $target.offset();

				var myPos = parsePosString(rule.my);
				var atPos = parsePosString(rule.at);

				var ruleOffsetX = 0, ruleOffsetY = 0;
				if (Array.isArray(rule.offset)) {
					ruleOffsetX = parseInt(rule.offset[0], 10) || 0;
					ruleOffsetY = parseInt(rule.offset[1], 10) || 0;
				} else if (rule.offset && typeof rule.offset === 'object') {
					ruleOffsetX = parseInt(rule.offset.x, 10) || 0;
					ruleOffsetY = parseInt(rule.offset.y, 10) || 0;
				}

				var applyX = rule.axis === 'x' || (rule.axis !== 'y' && myPos.hasH);
				var applyY = rule.axis === 'y' || (rule.axis !== 'x' && myPos.hasV);

				if (!myPos.hasH && !myPos.hasV && !rule.axis) {
					applyX = true;
					applyY = true;
				}

				if (applyX) {
					var myOffsetX = getCoordOffset(myPos.h, elemWidth);
					var atOffsetX = getCoordOffset(atPos.h, targetWidth);
					finalPosition.left = Math.round(targetOffset.left + atOffsetX - myOffsetX + myPos.shiftX + atPos.shiftX + ruleOffsetX);

					lastRuleX = {
						$target: $target,
						myPos: myPos,
						atPos: atPos,
						ruleOffsetX: ruleOffsetX
					};
				}

				if (applyY) {
					var myOffsetY = getCoordOffset(myPos.v, elemHeight);
					var atOffsetY = getCoordOffset(atPos.v, targetHeight);
					finalPosition.top = Math.round(targetOffset.top + atOffsetY - myOffsetY + myPos.shiftY + atPos.shiftY + ruleOffsetY);

					lastRuleY = {
						$target: $target,
						myPos: myPos,
						atPos: atPos,
						ruleOffsetY: ruleOffsetY
					};
				}
			});

			if (finalPosition.left === null && finalPosition.top === null)
				return;

			// --- ПОСТОБРАБОТКА КОЛЛИЗИЙ ---
			var collision = globalOptions.collision || 'none';
			if (collision !== 'none') {
				var $within = $(globalOptions.within);
				var isWin = $.isWindow($within[0]);

				var withinBounds = {
					left: isWin ? $within.scrollLeft() : $within.offset().left,
					top: isWin ? $within.scrollTop() : $within.offset().top,
					width: isWin ? $within.width() : $within.outerWidth(),
					height: isWin ? $within.height() : $within.outerHeight()
				};

				withinBounds.right = withinBounds.left + withinBounds.width;
				withinBounds.bottom = withinBounds.top + withinBounds.height;

				var doFlip = collision === 'flip' || collision === 'flipfit';
				var doFit = collision === 'fit' || collision === 'flipfit';

				// Корректировка X
				if (finalPosition.left !== null) {
					var overflowLeft = finalPosition.left < withinBounds.left;
					var overflowRight = finalPosition.left + elemWidth > withinBounds.right;

					if ((overflowLeft || overflowRight) && doFlip && lastRuleX) {
						var flippedMyH = flipToken(lastRuleX.myPos.h);
						var flippedAtH = flipToken(lastRuleX.atPos.h);

						var flippedMyOffsetX = getCoordOffset(flippedMyH, elemWidth);
						var flippedAtOffsetX = getCoordOffset(flippedAtH, lastRuleX.$target.outerWidth());
						var flippedLeft = Math.round(
										lastRuleX.$target.offset().left + flippedAtOffsetX - flippedMyOffsetX - lastRuleX.myPos.shiftX - lastRuleX.atPos.shiftX + lastRuleX.ruleOffsetX
										);

						if ((overflowLeft && flippedLeft >= withinBounds.left) || (overflowRight && flippedLeft + elemWidth <= withinBounds.right)) {
							finalPosition.left = flippedLeft;
							overflowLeft = finalPosition.left < withinBounds.left;
							overflowRight = finalPosition.left + elemWidth > withinBounds.right;
						}
					}

					if ((overflowLeft || overflowRight) && doFit) {
						finalPosition.left = Math.max(withinBounds.left, Math.min(finalPosition.left, withinBounds.right - elemWidth));
					}
				}

				// Корректировка Y
				if (finalPosition.top !== null) {
					var overflowTop = finalPosition.top < withinBounds.top;
					var overflowBottom = finalPosition.top + elemHeight > withinBounds.bottom;

					if ((overflowTop || overflowBottom) && doFlip && lastRuleY) {
						var flippedMyV = flipToken(lastRuleY.myPos.v);
						var flippedAtV = flipToken(lastRuleY.atPos.v);

						var flippedMyOffsetY = getCoordOffset(flippedMyV, elemHeight);
						var flippedAtOffsetY = getCoordOffset(flippedAtV, lastRuleY.$target.outerHeight());
						var flippedTop = Math.round(
										lastRuleY.$target.offset().top + flippedAtOffsetY - flippedMyOffsetY - lastRuleY.myPos.shiftY - lastRuleY.atPos.shiftY + lastRuleY.ruleOffsetY
										);

						if ((overflowTop && flippedTop >= withinBounds.top) || (overflowBottom && flippedTop + elemHeight <= withinBounds.bottom)) {
							finalPosition.top = flippedTop;
							overflowTop = finalPosition.top < withinBounds.top;
							overflowBottom = finalPosition.top + elemHeight > withinBounds.bottom;
						}
					}

					if ((overflowTop || overflowBottom) && doFit) {
						finalPosition.top = Math.max(withinBounds.top, Math.min(finalPosition.top, withinBounds.bottom - elemHeight));
					}
				}
			}

			$elem.offset(finalPosition);
		});
	};

})(window, window.jQuery, window.crazy74);

(function (window, $, crazy74) {
	'use strict';

	if (!$) return;

	// Дефолтные настройки
	const defaults = {
		icon: false,
		title: 'Окно',
		content: '',
		customClass: '',
		contentStyle: '',
		withBackDrop: true,
		destroyOnClose: true,
		closeOnEsc: true,
		duration: 150,
		width: null, // '500px' или 500
		height: null, // '400px' или 400
		labels: {
			close: 'Закрыть',
			title: 'Информационное окно'
		},
		'z-group': 'body > *, .csl-window-frame, .csl-window-backdrop, .-webkit-scrollbar',
		onClose: null
	};

	$.csl74Window = $.fn.csl74Window = function (options) {
		const opt = $.extend(true, {}, defaults, options);
		const optZIndex = {inc: 100, group: opt['z-group']};

		// Формирование BEM-подобных или префиксных классов
		const getClassName = (postfix) => {
			let cls = 'csl-window';
			if (postfix) cls += '-' + postfix;
			if (opt.customClass) {
				cls += ' ' + opt.customClass;
				if (postfix) cls += '-' + postfix;
			}
			return cls;
		};

		let $backdrop = null;
		let $frame = null;
		let $content = null;

		// Если вызов идет от элементов $(selector).cslWindow()
		const isElementCall = this && this.jquery && this.length > 0;

		// Элемент бакдропа
		if (opt.withBackDrop) {
			$backdrop = $('<div>', {class: getClassName('backdrop')}).appendTo('body');
			if (typeof $.fn.maxZIndex === 'function') {
				$backdrop.maxZIndex(optZIndex);
			}
		}

		// Основной фрейм
		$frame = $('<div>', {class: getClassName('frame')}).appendTo('body');
		if (typeof $.fn.maxZIndex === 'function') {
			$frame.maxZIndex(optZIndex);
		}

		// Шапка окна
		const $header = $('<div>', {class: getClassName('header')}).appendTo($frame);
		const $closeBtn = $('<button>', {
			type: 'button',
			class: getClassName('close'),
			title: opt.labels.close,
			html: '&times;'
		}).appendTo($header);

		let $title = $('<div>', {
			class: getClassName('title'),
			text: opt.title || opt.labels.title
		}).appendTo($header);
		if (typeof (opt.icon) == 'string') {
			$title.wrapInner('<span>');
			$('<span>', {class: 'icon ' + opt.icon}).prependTo($title);
		}

		// Контейнер под содержимое
		$content = $('<div>', {class: getClassName('content')}).appendTo($frame);
		if (opt.contentStyle) {
			$content.addClass(opt.contentStyle);
		}

		// Заполнение контентом
		if (isElementCall) {
			$content.append(this.show());
		} else if (opt.content) {
			$content.append(opt.content);
		}

		// Применение размеров
		if (opt.width) {
			$frame.css('width', typeof opt.width === 'number' ? `${opt.width}px` : opt.width);
		}
		if (opt.height) {
			$content.css('height', typeof opt.height === 'number' ? `${opt.height}px` : opt.height);
		}

		// Функция закрытия
		const close = () => {
			$(window).off('keydown.cslWindow');

			if ($backdrop) {
				$backdrop.fadeOut(opt.duration, () => {
					if (opt.destroyOnClose) $backdrop.remove();
				});
			}

			$frame.fadeOut(opt.duration, () => {
				if (opt.destroyOnClose) $frame.remove();
				if (typeof opt.onClose === 'function') {
					opt.onClose();
				}
			});
		};

		// Навешивание клика закрытия
		$closeBtn.on('click', close);

		// Закрытие по ESC
		if (opt.closeOnEsc) {
			$(window).on('keydown.cslWindow', (e) => {
				if (e.key === 'Escape' || e.keyCode === 27) {
					close();
				}
			});
		}

		// Показываем окно
		if ($backdrop) $backdrop.fadeIn(opt.duration);
		if (opt.position && typeof $.fn.csl74Position === 'function') {
			$frame.show().csl74Position(...opt.position);
			setTimeout(() => {
				$frame.csl74Position(...opt.position);
			}, 50);
		} else {
			$frame.addClass('csl-window-centered').fadeIn(opt.duration);
		}

		// Публичный интерфейс управления окном
		return {
			$frame: $frame,
			$content: $content,
			close: close,
			setTitle: function (newTitle) {
				$header.children('.' + getClassName('title')).text(newTitle);
			},
			setContent: function (newContent) {
				$content.empty().append(newContent);
			}
		};
	};

	$.fn.csl74Window.defaults = defaults;

})(window, window.jQuery, window.crazy74);

(function (window, $, crazy74) {
	'use strict';

	var ACTIVE_MENU = null;
	var ACTIVE_TRIGGER = null;

	function closeActiveMenu() {
		if (ACTIVE_MENU) {
			ACTIVE_MENU.remove();
			ACTIVE_MENU = null;
			ACTIVE_TRIGGER = null;
			$(document).off('click.csl74Menu keydown.csl74Menu');
		}
	}

	function renderAndShow(items, $trigger, opts) {
		if (!items || !items.length) return;

		const getClassName = (postfix, more) => {
			let cls = 'csl74-menu';
			if (postfix) cls += '-' + postfix;
			if (opts.customClass) {
				cls += ' ' + opts.customClass;
				if (postfix) cls += '-' + postfix;
			}
			if (more) cls += ' ' + more;
			return cls;
		};

		var $ul = $('<ul>', { class: getClassName('list')});

		items.forEach(function (item) {
			// 1. Разделитель
			if (item === '-') {
				$('<li>', { class: getClassName('divider')}).appendTo($ul);
				return;
			}

			var $li, that = {};

			const Renderer = function (item, opts, $trigger) {
				const $res = $('<li>', { class: getClassName('item')});
				if (typeof(item.label) !== 'string') {
					item.label = item.value;
				}
				let label = item.label;
				let cutted = typeof( crazy74?.string?.strcut ) === 'function'
					? crazy74.string.strcut(label, opts.maxlen)
					: label;
				let $label = $('<span>', { class: getClassName('label')}).text(cutted).appendTo($res);
				if (cutted !== label) {
					$label.attr('title', label);
				}
				if (item.icon) {
					$('<span>', { class: getClassName('icon', item.icon)}).prependTo($res);
				}
				if (item.disabled) $res.addClass('disabled');
				if (item.danger) $res.addClass('danger');
				return $res;
			}.bind(that);

			// 2. Нормализация сырой строки
			if (typeof item === 'string') {
				item = {
					value: item
				};
			}

			// 3. Кастомная или дефолтная отрисовка
			var customRenderer = item.render || opts.renderItem;
			if (typeof(customRenderer) === 'function') {
				that.super = Renderer.bind(that);
				$li = customRenderer.call(that, item, opts, $trigger);
			} else {
				$li = Renderer.call(that, item, opts, $trigger);
			}
			$li.appendTo($ul);

			// 4. Обработка клика по пункту
			$li.on('click', function (e) {
				if (item.disabled) return;
				e.stopPropagation();

				if (typeof item.onClick === 'function') {
					item.onClick(item, $trigger, e);
				}
				if (typeof opts.onSelect === 'function') {
					opts.onSelect(item.value || item.label, item, $trigger, e);
				}

				closeActiveMenu();
				if (typeof opts.onClose === 'function') opts.onClose($trigger);
			});
		});

		var $menu = $('<div>', { class: getClassName('container')}).append($ul);
		$('body').append($menu);
		ACTIVE_MENU = $menu;
		ACTIVE_TRIGGER = $trigger;

		// 5. Подготовка конфига позиционирования
		var posConfig = (crazy74 && crazy74.array && typeof crazy74.array.clone === 'function')
			? crazy74.array.clone(opts.position)
			: $.extend(true, [], opts.position);

		if (posConfig[0] && posConfig[0][0]) {
			posConfig[0][0].of = $trigger;
		}

		// 6. Позиционирование в два прохода
		if (typeof $.fn.csl74Position === 'function') {
			$menu.show().csl74Position(posConfig[0], posConfig[1]);
			setTimeout(function () {
				if (ACTIVE_MENU === $menu) {
					$menu.csl74Position(posConfig[0], posConfig[1]);
				}
			}, 50);
		} else {
			$menu.show();
		}

		if (typeof opts.onOpen === 'function') opts.onOpen($menu, $trigger);

		// 7. Навешивание глобальных обработчиков закрытия
		setTimeout(function () {
			$(document).on('click.csl74Menu', function () {
				closeActiveMenu();
				if (typeof opts.onClose === 'function') opts.onClose($trigger);
			});

			$(document).on('keydown.csl74Menu', function (e) {
				if (e.key === 'Escape') {
					closeActiveMenu();
					if (typeof opts.onClose === 'function') opts.onClose($trigger);
				}
			});
		}, 0);
	}

	$.fn.csl74Menu = function (options) {
		var opts = $.extend({
			position: [
				[{ my: 'left top', at: 'left bottom' }],
				{ collision: 'flipfit' }
			],
			items: [],
			maxlen: 50,
			renderItem: null,
			onSelect: null,
			onOpen: null,
			onClose: null
		}, options);

		return this.each(function () {
			var $trigger = $(this);

			$trigger.off('click.csl74Menu').on('click.csl74Menu', function (e) {
				e.preventDefault();
				e.stopPropagation();

				var isSameTrigger = ACTIVE_TRIGGER && ACTIVE_TRIGGER[0] === this;

				closeActiveMenu();

				// Если кликнули по той же кнопке — просто закрываем и выходим
				if (isSameTrigger) {
					if (typeof opts.onClose === 'function') opts.onClose($trigger);
					return;
				}

				var rawItems = typeof opts.items === 'function' ? opts.items($trigger) : opts.items;

				if (rawItems && typeof rawItems.then === 'function') {
					rawItems.then(function (resolvedItems) {
						renderAndShow(resolvedItems, $trigger, opts);
					});
				} else {
					renderAndShow(rawItems, $trigger, opts);
				}
			});
		});
	};

	$.csl74Menu = {
		close: closeActiveMenu
	};

})(window, window.jQuery, window.crazy74);

(function ($) {
	'use strict';

	$.fn.csl74Select = function (options) {
		var opts = $.extend({
			items: null,         // Те же items (Array, Function, Promise), что и в csl74Menu
			placeholder: '',
			onSelect: null
		}, options);

		return this.each(function () {
			var $select = $(this);
			if (!$select.is('select')) return;

			// 1. Оборачиваем select в .csl74-select, если ещё не обёрнут
			var $wrapper = $select.parent('.csl74-select');
			if (!$wrapper.length) {
				$select.wrap('<div class="csl74-select"></div>');
				$wrapper = $select.parent();
			}

			// Достраиваем минимальный DOM обёртки
			var $value = $wrapper.children('.csl74-select-value');
			if (!$value.length) {
				$value = $('<span>', { class: 'csl74-select-value' }).prependTo($wrapper);
			}

			if (!$wrapper.children('.csl74-icon-chevron-down').length) {
				$('<span>', { class: 'icon csl74-icon-chevron-down' }).appendTo($wrapper);
			}

			// 2. Если items переданы явно — синхронизируем их с нативным <select>
			if (opts.items && Array.isArray(opts.items)) {
				$select.empty();
				opts.items.forEach(function (item) {
					if (typeof item === 'string' && item === '-') return;
					$('<option>', {
						value: item.value !== undefined ? item.value : item.id,
						text: item.label !== undefined ? item.label : item.title,
						disabled: !!item.disabled,
						selected: !!item.selected
					}).appendTo($select);
				});
			}

			// Обновление отображаемого значения
			function syncValue() {
				var $opt = $select.find('option:selected');
				var txt = $opt.length ? $opt.text() : opts.placeholder;
				$value.text(txt);
			}

			$select.off('change.csl74Select').on('change.csl74Select', syncValue);
			syncValue();

			// 3. Собираем единый options для csl74Menu
			var menuOpts = $.extend({}, options, {
				// Если items не передали, вытягиваем их из option нативного select
				items: opts.items || function () {
					var res = [];
					$select.find('option').each(function () {
						var $o = $(this);
						res.push({
							value: $o.val(),
							label: $o.text(),
							disabled: $o.is(':disabled')
						});
					});
					return res;
				},
				// Перехватываем выбор пункта для синхронизации с select
				onSelect: function (val, item, e) {
					if ($select.val() !== val) {
						$select.val(val).trigger('change');
					} else {
						syncValue();
					}

					if (typeof opts.onSelect === 'function') {
						opts.onSelect.call(this, val, item, e);
					}
				},
				onOpen: function (menuEl, triggerEl) {
					$wrapper.addClass('is-open');
					if (typeof opts.onOpen === 'function') {
						opts.onOpen.call(this, menuEl, triggerEl);
					}
				},
				onClose: function (menuEl, triggerEl) {
					$wrapper.removeClass('is-open');
					if (typeof opts.onClose === 'function') {
						opts.onClose.call(this, menuEl, triggerEl);
					}
				}
			});

			// Инициализируем csl74Menu прямо на обёртке
			$wrapper.csl74Menu(menuOpts);
		});
	};
})(jQuery);