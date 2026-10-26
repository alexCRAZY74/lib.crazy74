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

		// Перепозиционирование окна
		const realign = () => {
			if (opt.position && typeof $.fn.csl74Position === 'function') {
				$frame.csl74Position(...opt.position);
			}
		};

		// Показываем окно
		if ($backdrop) $backdrop.fadeIn(opt.duration);
		if (opt.position && typeof $.fn.csl74Position === 'function') {
			$frame.show();
			realign();
			setTimeout(realign, 50);
		} else {
			$frame.addClass('csl-window-centered').fadeIn(opt.duration);
		}

		// Публичный интерфейс управления окном
		return {
			$frame: $frame,
			$content: $content,
			close: close,
			realign: realign,
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
				if (item.selected || item.active) $res.addClass('selected');
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
				var currentVal = $select.val();
				$select.empty();
				opts.items.forEach(function (item) {
					if (typeof item === 'string' && item === '-') return;
					var val = item.value !== undefined ? item.value : item.id;
					var isSelected = item.selected || (currentVal !== null && currentVal !== undefined && String(val) === String(currentVal));
					var $option = $('<option>', {
						value: val,
						text: item.label !== undefined ? item.label : item.title,
						disabled: !!item.disabled,
						selected: !!isSelected
					});
					if (item.icon) {
						$option.data('icon', item.icon);
					}
					$option.appendTo($select);
				});
			}

			// Обновление отображаемого значения
			function syncValue1() {
				var $opt = $select.find('option:selected');
				var txt = $opt.length ? $opt.text() : opts.placeholder;

				$value.empty(); // Очищаем предыдущее содержимое
				if ($opt.length) {
					var iconClass = $opt.data('icon');
					if (iconClass) {
						$('<span>', { class: 'icon ' + iconClass }).appendTo($value);
					}
					$value.append(txt);
				} else {
					$value.text(opts.placeholder);
				}
			}
      
      function syncValue() {
        var $opt = $select.find('option:selected');
        var txt = $opt.length ? $opt.text() : opts.placeholder;

        $value.empty(); // Очищаем предыдущее содержимое
        if ($opt.length) {
          var iconClass = $opt.data('icon');
          if (iconClass) {
            $('<span>', {class: 'icon ' + iconClass}).appendTo($value);
            $('<span>', {class: 'text'}).text(txt).appendTo($value);
          } else {
            $value.append(txt);
          }
        } else {
          $value.text(opts.placeholder);
        }
      }

			$select.off('change.csl74Select').on('change.csl74Select', syncValue);
			syncValue();

			// 3. Собираем единый options для csl74Menu
			var menuOpts = $.extend({}, options, {
				items: function () {
					var currentVal = String($select.val());

					var markSelected = function (list) {
						return list.map(function (item) {
							if (typeof item === 'string') {
								if (item === '-') return item;
								item = { value: item, label: item };
							}
							var val = item.value !== undefined ? item.value : item.id;
							return $.extend({}, item, {
								selected: item.selected !== undefined ? item.selected : (String(val) === currentVal)
							});
						});
					};

					if (opts.items) {
						var raw = typeof opts.items === 'function' ? opts.items($select) : opts.items;
						if (raw && typeof raw.then === 'function') {
							return raw.then(markSelected);
						}
						return Array.isArray(raw) ? markSelected(raw) : raw;
					}

					var res = [];
					$select.find('option').each(function () {
						var $o = $(this);
						var val = $o.val();
						res.push({
              value: val,
              label: $o.text(),
              disabled: $o.is(':disabled'),
              selected: $o.is(':selected'),
              icon: $o.data('icon') // Передаем иконку, если есть
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

(function ($) {
	'use strict';

	const TabControl = function ($element, options) {
		this.$element = $element;
		this.options = $.extend({
			active: 0,
			onSelect: null
		}, options);

		this.tabs = [];
		this._init();
	};

	TabControl.prototype._init = function () {
		var self = this;
		this.$element.addClass('csl74-tabs');

		this.$nav = this.$element.children('.csl74-tabs-nav');
		if (!this.$nav.length) {
			this.$nav = $('<div>', { class: 'csl74-tabs-nav' }).prependTo(this.$element);
		}

		this.$panels = this.$element.children('.csl74-tabs-panels');
		var $initial = this.$element.children().not(this.$nav).not(this.$panels);
		if (!this.$panels.length) {
			this.$panels = $('<div>', { class: 'csl74-tabs-panels' }).appendTo(this.$element);
		}

		var activeIndex = -1;
		if ($initial.length) {
			$initial.each(function (i) {
				var $panel = $(this);
				$panel.appendTo(self.$panels);
				var title = $panel.data('title') || $panel.attr('title') || '';
				var icon = $panel.data('icon') || '';
				if ($panel.hasClass('active') || $panel.hasClass('is-active') || $panel.data('active')) {
					activeIndex = i;
				}
				self._createTab(title, icon, $panel);
			});
		}

		if (this.tabs.length > 0) {
			this.selectTab(activeIndex >= 0 ? activeIndex : (this.options.active || 0));
		}
	};

	TabControl.prototype._createTab = function (title, icon, $panel) {
		var self = this;
		$panel.addClass('csl74-tabs-panel');

		var $navItem = $('<div>', { class: 'csl74-tabs-item' });
		if (icon) {
			$('<span>', { class: 'icon ' + icon }).appendTo($navItem);
		}
		$('<span>', { class: 'csl74-tabs-title', text: title || ('Вкладка ' + (this.tabs.length + 1)) }).appendTo($navItem);

		var tabData = {
			title: title,
			icon: icon,
			$navItem: $navItem,
			$panel: $panel
		};

		$panel.data('csl74Tab', tabData);
		$panel.data('csl74Tabs', this);

		$navItem.on('click', function () {
			self.selectTab(tabData);
		});

		$navItem.appendTo(this.$nav);
		this.tabs.push(tabData);

		return tabData;
	};

	TabControl.prototype.addTab = function (title, icon, activate) {
		var $panel = $('<div>', { class: 'csl74-tabs-panel' }).appendTo(this.$panels);
		var tabData = this._createTab(title, icon, $panel);

		if (activate === true || this.tabs.length === 1) {
			this.selectTab(tabData);
		}

		return $panel;
	};

	TabControl.prototype.selectTab = function (target) {
		var tabToSelect = null;
		if (typeof target === 'number') {
			tabToSelect = this.tabs[target] || null;
		} else if (target && target.$panel) {
			tabToSelect = target;
		} else if (target && target.jquery) {
			for (var i = 0; i < this.tabs.length; i++) {
				if (this.tabs[i].$panel[0] === target[0] || this.tabs[i].$navItem[0] === target[0]) {
					tabToSelect = this.tabs[i];
					break;
				}
			}
		}

		if (!tabToSelect) return this;

		var selectedIndex = this.tabs.indexOf(tabToSelect);
		this.tabs.forEach(function (t) {
			var isActive = (t === tabToSelect);
			t.$navItem.toggleClass('is-active active', isActive);
			t.$panel.toggleClass('is-active active', isActive);
		});

		if (typeof this.options.onSelect === 'function') {
			this.options.onSelect.call(this, selectedIndex, tabToSelect);
		}

		return this;
	};

	TabControl.prototype.getActiveTab = function () {
		for (var i = 0; i < this.tabs.length; i++) {
			if (this.tabs[i].$navItem.hasClass('is-active')) {
				return this.tabs[i];
			}
		}
		return null;
	};

	TabControl.prototype.getTab = function (index) {
		return this.tabs[index] || null;
	};

	$.fn.csl74Tabs = function (options) {
		var firstInstance = null;

		this.each(function () {
			var $el = $(this);
			var instance = $el.data('csl74Tabs');
			if (!instance) {
				instance = new TabControl($el, options);
				$el.data('csl74Tabs', instance);
			}
			if (!firstInstance) {
				firstInstance = instance;
			}
		});

		return firstInstance;
	};

})(jQuery);

(function (window, $, crazy74) {
	'use strict';

	if (!$) return;

	var ACTIVE_PICKER = null;
	var ACTIVE_INPUT = null;

	var SVG_ICONS = {
		prev: '<svg viewBox="0 0 16 16" width="12" height="12"><path fill="currentColor" d="M10.5 3.5L5.5 8l5 4.5-.7.8L4 8l5.8-5.3z"/></svg>',
		next: '<svg viewBox="0 0 16 16" width="12" height="12"><path fill="currentColor" d="M5.5 3.5l5 4.5-5 4.5.7.8L12 8 6.2 2.7z"/></svg>',
		prevYear: '<svg viewBox="0 0 16 16" width="12" height="12"><path fill="currentColor" d="M8.5 3.5L3.5 8l5 4.5-.7.8L2 8l5.8-5.3zM14 3.5L9 8l5 4.5-.7.8L7.5 8l5.8-5.3z"/></svg>',
		nextYear: '<svg viewBox="0 0 16 16" width="12" height="12"><path fill="currentColor" d="M2 3.5l5 4.5-5 4.5.7.8L8.5 8 2.7 2.7zM7.5 3.5l5 4.5-5 4.5.7.8L14 8 8.2 2.7z"/></svg>',
		up: '<svg viewBox="0 0 16 16" width="10" height="10"><path fill="currentColor" d="M3.5 10.5L8 5.5l4.5 5-.8.7L8 7l-3.7 4.2z"/></svg>',
		down: '<svg viewBox="0 0 16 16" width="10" height="10"><path fill="currentColor" d="M3.5 5.5L8 10.5l4.5-5-.8-.7L8 9 4.3 4.8z"/></svg>'
	};

	var DEFAULT_MONTHS = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
	var DEFAULT_DAYS_SHORT = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

	function getMonthNames() {
		if (crazy74 && crazy74.lang) {
			var list = crazy74.lang.get('date', 'months') || crazy74.lang.get('months');
			if (Array.isArray(list) && list.length === 12) return list;
		}
		return DEFAULT_MONTHS;
	}

	function getDayNamesShort() {
		if (crazy74 && crazy74.lang) {
			var list = crazy74.lang.get('date', 'days_short') || crazy74.lang.get('days_short');
			if (Array.isArray(list) && list.length === 7) return list;
		}
		return DEFAULT_DAYS_SHORT;
	}

	function pad(num) {
		return num < 10 ? '0' + num : '' + num;
	}

	function parseDate(val) {
		if (!val) return null;
		if (val instanceof Date && !isNaN(val.getTime())) return new Date(val.getTime());
		if (crazy74 && crazy74.date && typeof crazy74.date.parseDate === 'function') {
			var parsed = crazy74.date.parseDate(val);
			if (parsed instanceof Date && !isNaN(parsed.getTime())) return parsed;
		}
		if (typeof val === 'string') {
			var d = new Date(val);
			if (!isNaN(d.getTime())) return d;
			var m = val.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?/);
			if (m) {
				return new Date(
					parseInt(m[3], 10),
					parseInt(m[2], 10) - 1,
					parseInt(m[1], 10),
					parseInt(m[4] || 0, 10),
					parseInt(m[5] || 0, 10),
					parseInt(m[6] || 0, 10)
				);
			}
		}
		return null;
	}

	function formatDate(date, opts) {
		if (!date) return '';
		if (opts.format && crazy74 && crazy74.date && typeof crazy74.date.formatLocale === 'function') {
			return crazy74.date.formatLocale(date, {withTime: opts.pickTime});
		}
		var day = pad(date.getDate());
		var month = pad(date.getMonth() + 1);
		var year = date.getFullYear();
		var hours = pad(date.getHours());
		var minutes = pad(date.getMinutes());
		var seconds = pad(date.getSeconds());

		if (opts.pickDate && opts.pickTime) {
			return opts.pickSeconds
				? day + '.' + month + '.' + year + ' ' + hours + ':' + minutes + ':' + seconds
				: day + '.' + month + '.' + year + ' ' + hours + ':' + minutes;
		}
		if (opts.pickDate) {
			return day + '.' + month + '.' + year;
		}
		if (opts.pickTime) {
			return opts.pickSeconds ? hours + ':' + minutes + ':' + seconds : hours + ':' + minutes;
		}
		return '';
	}

	function closeActivePicker() {
		if (ACTIVE_PICKER) {
			var p = ACTIVE_PICKER;
			var trig = ACTIVE_INPUT;
			ACTIVE_PICKER = null;
			ACTIVE_INPUT = null;
			$(document).off('click.csl74DatePicker keydown.csl74DatePicker');
			if (p.opts && typeof p.opts.onClose === 'function') {
				p.opts.onClose(trig);
			}
			p.$el.remove();
		}
	}

	function DatePickerWidget($input, options) {
		this.$input = $input;
		this.opts = $.extend({
			pickDate: true,
			pickTime: true,
			pickSeconds: false,
			format: null,
			minDate: null,
			maxDate: null,
			stepMinute: 1,
			stepSecond: 1,
			customClass: '',
			position: [
				[{ my: 'left top', at: 'left bottom' }],
				{ collision: 'flipfit' }
			],
			onSelect: null,
			onOpen: null,
			onClose: null
		}, options);

		this.viewMode = this.opts.pickDate ? 'days' : 'time';
		var initial = parseDate($input.val()) || new Date();
		this.selectedDate = new Date(initial.getTime());
		this.viewDate = new Date(initial.getFullYear(), initial.getMonth(), 1);
		this.$el = null;
	}

	DatePickerWidget.prototype.render = function () {
		var self = this;
		var cls = 'csl74-datepicker';
		if (this.opts.customClass) cls += ' ' + this.opts.customClass;

		if (this.$el) this.$el.empty();
		else {
			this.$el = $('<div>', { class: cls });
			this.$el.on('click.csl74DatePicker', function (e) {
				e.stopPropagation();
			});
			$('body').append(this.$el);
		}

		if (this.opts.pickDate) {
			var $dateContainer = $('<div>', { class: 'csl74-datepicker-date-view' }).appendTo(this.$el);
			if (this.viewMode === 'days') this._renderDays($dateContainer);
			else if (this.viewMode === 'months') this._renderMonths($dateContainer);
			else if (this.viewMode === 'years') this._renderYears($dateContainer);
		}

		if (this.opts.pickTime) {
			this._renderTime(this.$el);
		}

		this._renderFooter(this.$el);
		this._realign();
	};

	DatePickerWidget.prototype._renderDays = function ($container) {
		var self = this;
		var months = getMonthNames();
		var daysShort = getDayNamesShort();

		var $header = $('<div>', { class: 'csl74-datepicker-header' }).appendTo($container);
		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-prev-year', html: SVG_ICONS.prevYear })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setFullYear(self.viewDate.getFullYear() - 1); self.render(); }).appendTo($header);
		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-prev-month', html: SVG_ICONS.prev })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setMonth(self.viewDate.getMonth() - 1); self.render(); }).appendTo($header);

		$('<span>', { class: 'csl74-datepicker-title', text: months[self.viewDate.getMonth()] + ' ' + self.viewDate.getFullYear() })
			.on('click', function (e) { e.stopPropagation(); self.viewMode = 'months'; self.render(); }).appendTo($header);

		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-next-month', html: SVG_ICONS.next })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setMonth(self.viewDate.getMonth() + 1); self.render(); }).appendTo($header);
		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-next-year', html: SVG_ICONS.nextYear })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setFullYear(self.viewDate.getFullYear() + 1); self.render(); }).appendTo($header);

		var $table = $('<table>', { class: 'csl74-datepicker-grid' }).appendTo($container);
		var $thead = $('<thead>').appendTo($table);
		var $trHead = $('<tr>').appendTo($thead);
		daysShort.forEach(function (d) { $('<th>').text(d).appendTo($trHead); });

		var $tbody = $('<tbody>').appendTo($table);
		var y = self.viewDate.getFullYear();
		var m = self.viewDate.getMonth();
		var firstDay = new Date(y, m, 1);
		var startOffset = (firstDay.getDay() + 6) % 7;
		var curDate = new Date(y, m, 1 - startOffset);
		var today = new Date();

		for (var row = 0; row < 6; row++) {
			var $tr = $('<tr>').appendTo($tbody);
			for (var col = 0; col < 7; col++) {
				var cellDate = new Date(curDate.getTime());
				var $td = $('<td>', { class: 'csl74-datepicker-cell', text: cellDate.getDate() });

				if (cellDate.getMonth() !== m) $td.addClass('is-muted');
				if (cellDate.toDateString() === today.toDateString()) $td.addClass('is-today');
				if (self.selectedDate && cellDate.toDateString() === self.selectedDate.toDateString()) $td.addClass('is-selected');

				(function (d) {
					$td.on('click', function (e) {
						e.stopPropagation();
						self.selectedDate.setFullYear(d.getFullYear(), d.getMonth(), d.getDate());
						if (!self.opts.pickTime) {
							self.commit();
							closeActivePicker();
						} else {
							self.commit();
							self.render();
						}
					});
				})(cellDate);

				$td.appendTo($tr);
				curDate.setDate(curDate.getDate() + 1);
			}
		}
	};

	DatePickerWidget.prototype._renderMonths = function ($container) {
		var self = this;
		var months = getMonthNames();
		var y = self.viewDate.getFullYear();

		var $header = $('<div>', { class: 'csl74-datepicker-header' }).appendTo($container);
		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-prev-year', html: SVG_ICONS.prevYear })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setFullYear(y - 1); self.render(); }).appendTo($header);

		$('<span>', { class: 'csl74-datepicker-title', text: y })
			.on('click', function (e) { e.stopPropagation(); self.viewMode = 'years'; self.render(); }).appendTo($header);

		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-next-year', html: SVG_ICONS.nextYear })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setFullYear(y + 1); self.render(); }).appendTo($header);

		var $grid = $('<div>', { class: 'csl74-datepicker-block-grid' }).appendTo($container);
		months.forEach(function (name, idx) {
			var $cell = $('<div>', { class: 'csl74-datepicker-block-cell', text: name.substring(0, 3) });
			if (self.selectedDate && self.selectedDate.getFullYear() === y && self.selectedDate.getMonth() === idx) {
				$cell.addClass('is-selected');
			}
			$cell.on('click', function (e) {
				e.stopPropagation();
				self.viewDate.setMonth(idx);
				self.viewMode = 'days';
				self.render();
			}).appendTo($grid);
		});
	};

	DatePickerWidget.prototype._renderYears = function ($container) {
		var self = this;
		var y = self.viewDate.getFullYear();
		var startYear = Math.floor(y / 10) * 10;
		var endYear = startYear + 9;

		var $header = $('<div>', { class: 'csl74-datepicker-header' }).appendTo($container);
		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-prev-decade', html: SVG_ICONS.prevYear })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setFullYear(startYear - 10); self.render(); }).appendTo($header);

		$('<span>', { class: 'csl74-datepicker-title', text: startYear + ' – ' + endYear }).appendTo($header);

		$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-next-decade', html: SVG_ICONS.nextYear })
			.on('click', function (e) { e.stopPropagation(); self.viewDate.setFullYear(startYear + 10); self.render(); }).appendTo($header);

		var $grid = $('<div>', { class: 'csl74-datepicker-block-grid' }).appendTo($container);
		for (var curr = startYear - 1; curr <= endYear + 1; curr++) {
			var $cell = $('<div>', { class: 'csl74-datepicker-block-cell', text: curr });
			if (curr < startYear || curr > endYear) $cell.addClass('is-muted');
			if (self.selectedDate && self.selectedDate.getFullYear() === curr) $cell.addClass('is-selected');

			(function (chosen) {
				$cell.on('click', function (e) {
					e.stopPropagation();
					self.viewDate.setFullYear(chosen);
					self.viewMode = 'months';
					self.render();
				});
			})(curr);
			$cell.appendTo($grid);
		}
	};

	DatePickerWidget.prototype._renderTime = function ($container) {
		var self = this;
		var $time = $('<div>', { class: 'csl74-datepicker-time' }).appendTo($container);

		function makeSpinner(val, max, onStep) {
			var $wrap = $('<div>', { class: 'csl74-datepicker-time-unit' });
			$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-time-up', html: SVG_ICONS.up })
				.on('click', function (e) { e.stopPropagation(); onStep(1); self.commit(); self.render(); }).appendTo($wrap);
			$('<span>', { class: 'csl74-datepicker-time-val', text: pad(val) }).appendTo($wrap);
			$('<button>', { type: 'button', class: 'csl74-datepicker-btn csl74-datepicker-time-down', html: SVG_ICONS.down })
				.on('click', function (e) { e.stopPropagation(); onStep(-1); self.commit(); self.render(); }).appendTo($wrap);
			return $wrap;
		}

		var hours = this.selectedDate.getHours();
		var minutes = this.selectedDate.getMinutes();
		var seconds = this.selectedDate.getSeconds();

		$time.append(makeSpinner(hours, 23, function (dir) {
			self.selectedDate.setHours((hours + dir + 24) % 24);
		}));
		$time.append($('<span>', { class: 'csl74-datepicker-time-sep', text: ':' }));
		$time.append(makeSpinner(minutes, 59, function (dir) {
			var step = self.opts.stepMinute || 1;
			self.selectedDate.setMinutes((minutes + dir * step + 60) % 60);
		}));

		if (this.opts.pickSeconds) {
			$time.append($('<span>', { class: 'csl74-datepicker-time-sep', text: ':' }));
			$time.append(makeSpinner(seconds, 59, function (dir) {
				var step = self.opts.stepSecond || 1;
				self.selectedDate.setSeconds((seconds + dir * step + 60) % 60);
			}));
		}
	};

	DatePickerWidget.prototype._renderFooter = function ($container) {
		var self = this;
		var $footer = $('<div>', { class: 'csl74-datepicker-footer' }).appendTo($container);
		var todayText = (crazy74 && crazy74.lang && (crazy74.lang.get('date', 'now') || crazy74.lang.get('now'))) || 'сейчас';

		$('<button>', { type: 'button', class: 'csl74-datepicker-today-btn', text: todayText })
			.on('click', function (e) {
				e.stopPropagation();
				var now = new Date();
				self.selectedDate = now;
				self.viewDate = new Date(now.getFullYear(), now.getMonth(), 1);
				self.commit();
				if (!self.opts.pickTime) {
					closeActivePicker();
				} else {
					self.render();
				}
			}).appendTo($footer);
	};

	DatePickerWidget.prototype.commit = function () {
		var formatted = formatDate(this.selectedDate, this.opts);
		this.$input.val(formatted).trigger('change');
		if (typeof this.opts.onSelect === 'function') {
			this.opts.onSelect(this.selectedDate, formatted, this.$input);
		}
	};

	DatePickerWidget.prototype._realign = function () {
		if (!this.$el || typeof $.fn.csl74Position !== 'function') return;
		var posConfig = (crazy74 && crazy74.array && typeof crazy74.array.clone === 'function')
			? crazy74.array.clone(this.opts.position)
			: $.extend(true, [], this.opts.position);

		if (posConfig[0] && posConfig[0][0]) {
			posConfig[0][0].of = this.$input;
		}
		this.$el.show().csl74Position(posConfig[0], posConfig[1]);
	};

	$.fn.csl74DatePicker = $.fn.cls74DatePicker = function (options) {
		return this.each(function () {
			var $target = $(this);
			var $input, $trigger;

			if ($target.is('input')) {
				$input = $target;
				if (options && options.trigger) {
					if (typeof options.trigger === 'string') {
						$trigger = $input.siblings(options.trigger);
						if (!$trigger.length) {
							$trigger = $input.closest('.control-group, .form-group, .input-group, div').find(options.trigger);
						}
					} else if (options.trigger === true) {
						$trigger = $input.siblings('button, .speed-button, .btn');
					} else {
						$trigger = $(options.trigger);
					}
				}
			} else {
				$input = $target.find('input[type="text"], input:not([type]), input').first();
				var triggerSelector = (options && typeof options.trigger === 'string')
					? options.trigger
					: 'button, .speed-button, .btn';
				$trigger = $target.find(triggerSelector);
			}

			if (!$input || !$input.length) return;

			var opts = $.extend({
				openOnInput: !(options && (options.trigger || !$target.is('input')))
			}, options);

			var openPicker = function (e) {
				e.preventDefault();
				e.stopPropagation();

				var isSame = ACTIVE_INPUT && ACTIVE_INPUT[0] === $input[0];
				closeActivePicker();
				if (isSame) return;

				var widget = new DatePickerWidget($input, opts);
				widget.render();
				ACTIVE_PICKER = widget;
				ACTIVE_INPUT = $input;

				if (typeof widget.opts.onOpen === 'function') {
					widget.opts.onOpen(widget.$el, $input);
				}

				setTimeout(function () {
					$(document).on('click.csl74DatePicker', function () {
						closeActivePicker();
					});
					$(document).on('keydown.csl74DatePicker', function (ev) {
						if (ev.key === 'Escape') {
							closeActivePicker();
						}
					});
				}, 0);
			};

			$input.off('click.csl74DatePicker');
			if (opts.openOnInput) {
				$input.on('click.csl74DatePicker', openPicker);
			}

			if ($trigger && $trigger.length) {
				$trigger.off('click.csl74DatePicker').on('click.csl74DatePicker', openPicker);
			}
		});
	};

	$.csl74DatePicker = $.cls74DatePicker = {
		close: closeActivePicker
	};

})(window, window.jQuery, window.crazy74);
