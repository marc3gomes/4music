/*eslint-disable */
/* jscs:disable */

function _inheritsLoose(subClass, superClass) { subClass.prototype = Object.create(superClass.prototype); subClass.prototype.constructor = subClass; _setPrototypeOf(subClass, superClass); }

function _setPrototypeOf(o, p) { _setPrototypeOf = Object.setPrototypeOf || function _setPrototypeOf(o, p) { o.__proto__ = p; return o; }; return _setPrototypeOf(o, p); }

define(["require", "jquery", "knockout", "mage/translate", "Magento_PageBuilder/js/events", "slick", "underscore", "Magento_PageBuilder/js/config", "Magento_PageBuilder/js/content-type-menu/hide-show-option", "Magento_PageBuilder/js/content-type/preview", "Blueskytechco_PageBuilderCustom/js/resource/packery/packery.pkgd"], function (require, _jquery, _knockout, _translate, _events, _slick, _underscore, _config, _hideShowOption, _preview, Packery) {
  var Preview = /*#__PURE__*/function (_preview2) {
    "use strict";

    _inheritsLoose(Preview, _preview2);

    function Preview(contentType, config, observableUpdater) {
      var _this;

      _this = _preview2.call(this, contentType, config, observableUpdater) || this;
      _this.displayPreview = _knockout.observable(false);
      _this.previewElement = _jquery.Deferred();
      _this.widgetUnsanitizedHtml = _knockout.observable();
      _this.slidesToShow = 5;
      _this.lookbookItemSelector = ".elementor-lookbook-item";
      _this.centerModeClass = "center-mode";
      _this.messages = {
        EMPTY: (0, _translate)("Empty Lookbook Photos."),
        NO_RESULTS: (0, _translate)("No lookbook photos were found matching your condition."),
        LOADING: (0, _translate)("Loading..."),
        UNKNOWN_ERROR: (0, _translate)("An unknown error occurred. Please try again.")
      };
      _this.ignoredKeysForBuild = ["margins_and_padding", "border", "border_color", "border_radius", "border_width", "css_classes", "text_align"];
      _this.placeholderText = _knockout.observable(_this.messages.EMPTY);

      _events.on("contentType:redrawAfter", function (args) {
        if (_this.element && _this.element.children) {
          var $element = (0, _jquery)(_this.element.children).find('.widget-lookbook-wrapper');

          if (args.element && $element.closest(args.element).length) {
            $element.slick("setPosition");
          }
        }
      });

      _events.on("stage:" + _this.contentType.stageId + ":viewportChangeAfter", function (args) {
        var viewports = _config.getConfig("viewports");
        if (_this.element && _this.appearance() === "carousel") {
          _this.slidesToShow = parseFloat(_this.getColBySetting(args.viewport));
          _this.destroySlider();
          _this.initSlider();
        }
        else if(_this.element && _this.appearance() === "mansoy"){
          _this.destroyMansoy();
          _this.initMansoy();
        }
        else if(_this.element && _this.appearance() === "gridlist"){
          _this.initGrid();
        }
      });

      return _this;
    }
    
    var _proto = Preview.prototype;

    _proto.retrieveOptions = function retrieveOptions() {
      var options = _preview2.prototype.retrieveOptions.call(this);

      options.hideShow = new _hideShowOption({
        preview: this,
        icon: _hideShowOption.showIcon,
        title: _hideShowOption.showText,
        action: this.onOptionVisibilityToggle,
        classes: ["hide-show-content-type"],
        sort: 40
      });
      return options;
    };

    _proto.getColBySetting = function getColBySetting(current_viewport) {
      var get_lable = current_viewport;
      var data = this.contentType.dataStore.getState();
      var item = 4;
      if(get_lable == 'desktop'){
        item = data.col_xl;
      }
      else if(get_lable == 'tablet'){
        item = data.col_md;
      }
      else if(get_lable == 'mobile'){
        item = data.col_sm;
      }
      return item;
    };

    _proto.onAfterRender = function onAfterRender(element) {
      this.element = element;
      this.previewElement.resolve(element);
      this.initSlider();
      this.initMansoy();
      this.initGrid();
    };

    _proto.afterObservablesUpdated = function afterObservablesUpdated() {
      var _this2 = this;

      _preview2.prototype.afterObservablesUpdated.call(this);

      var data = this.contentType.dataStore.getState();

      if (this.hasDataChanged(this.previousData, data)) {
        this.displayPreview(false);
        if (typeof data.lookbook_id !== "object") {
          this.placeholderText(this.messages.EMPTY);
          return;
        }

        var url = _config.getConfig("preview_url");

        var requestConfig = {
          method: "POST",
          data: {
            role: this.config.name,
            directive: this.data.main.html()
          }
        };
        this.placeholderText(this.messages.LOADING);

        _jquery.ajax(url, requestConfig).done(function (response) {
          if (typeof response.data !== "object" || !Boolean(response.data.content)) {
            _this2.placeholderText(_this2.messages.NO_RESULTS);

            return;
          }

          if (response.data.error) {
            _this2.widgetUnsanitizedHtml(response.data.error);
          } else {
            _this2.widgetUnsanitizedHtml(response.data.content);

            _this2.displayPreview(true);
          }

          _this2.previewElement.done(function () {
            (0, _jquery)(_this2.element).trigger("contentUpdated");
          });
        }).fail(function () {
          _this2.placeholderText(_this2.messages.UNKNOWN_ERROR);
        });
      }

      this.previousData = Object.assign({}, data);
    };

    _proto.initSlider = function initSlider() {
      if (this.element && this.appearance() === "carousel") {
        (0, _jquery)(this.element.children).find('.widget-lookbook-wrapper').slick(this.buildSlickConfig());
      }
    };

    _proto.initGrid = function initGrid() {
      if (this.element && this.appearance() === "gridlist") {
        var $gridItems = (0, _jquery)(this.element.children).find('.elementor-lookbook-item');
        var currentViewport = this.viewport();
        $gridItems.attr('class', '');
        $gridItems.attr('class', 'elementor-lookbook-item item-hover col-'+this.getColBySetting(currentViewport));
      }
    };

    _proto.initMansoy = function initMansoy() {
      if (this.element && this.appearance() === "mansoy") {
        var $lookbookContainer = (0, _jquery)(this.element.children).find('.widget-lookbook-wrapper');
        if($lookbookContainer.find('.elementor-lookbook-item').length > 5){
          var currentViewport = this.viewport();
          var col_fixed = 'col-lg-3';
          if(currentViewport == 'desktop'){
            col_fixed = 'col-lg-3';
          }
          else if(currentViewport == 'tablet'){
            col_fixed = 'col-md-4';
          }
          else if(currentViewport == 'mobile'){
            col_fixed = 'col-6';
          }
          var $gridItems = (0, _jquery)(this.element.children).find('.elementor-lookbook-item');
          $gridItems.attr('class', '');
          $gridItems.attr('class', 'elementor-lookbook-item item-hover '+col_fixed);
          require( [ 'jquery-bridget/jquery-bridget' ], function( jQueryBridget ) {
              jQueryBridget('packery', Packery, (0, _jquery));
              $lookbookContainer.packery({
                  itemSelector: '.elementor-lookbook-item',
                  columnWidth: '.elementor-lookbook-item',
                  gutter: 0,
                  percentPosition: true,
                  originLeft: true 
              });
          });
        }
      }
    };

    _proto.destroyMansoy = function destroyMansoy() {
    
    };

    _proto.destroySlider = function destroySlider() {
      (0, _jquery)(this.element.children).find('.widget-lookbook-wrapper').slick("unslick");
    };

    _proto.buildSlickConfig = function buildSlickConfig() {
      var attributes = this.data.main.attributes();
      var postCount = (0, _jquery)(this.widgetUnsanitizedHtml()).find(this.lookbookItemSelector).length;

      var viewports = _config.getConfig("viewports");

      var currentViewport = this.viewport();
      var carouselMode = attributes["data-carousel-mode"];
      var config = {
        slidesToShow: this.slidesToShow,
        slidesToScroll: this.slidesToShow,
        dots: attributes["data-show-dots"] === "true",
        arrows: attributes["data-show-arrows"] === "true",
        autoplay: attributes["data-autoplay"] === "true",
        autoplaySpeed: parseFloat(attributes["data-autoplay-speed"])
      };
    
      var slidesToShow = parseFloat(this.getColBySetting(currentViewport));
      config.slidesToShow = parseFloat(slidesToShow);

      if (attributes["data-carousel-mode"] === "continuous" && postCount > config.slidesToShow) {
        config.centerPadding = attributes["data-center-padding"];
        config.centerMode = true;
        (0, _jquery)(this.element).addClass(this.centerModeClass);
      } else {
        config.infinite = attributes["data-infinite-loop"] === "true";
        (0, _jquery)(this.element).removeClass(this.centerModeClass);
      }
      config.rows = parseFloat(attributes["data-slick-rows"]);
      return config;
    };

    _proto.hasDataChanged = function hasDataChanged(previousData, newData) {
      previousData = _underscore.omit(previousData, this.ignoredKeysForBuild);
      newData = _underscore.omit(newData, this.ignoredKeysForBuild);
      return !_underscore.isEqual(previousData, newData);
    };

    return Preview;
  }(_preview);

  return Preview;
});
//# sourceMappingURL=preview.js.map