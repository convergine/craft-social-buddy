/*
 * ATTENTION: The "eval" devtool has been used (maybe by default in mode: "development").
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/css/style.scss":
/*!****************************!*\
  !*** ./src/css/style.scss ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

eval("__webpack_require__.r(__webpack_exports__);\n// extracted by mini-css-extract-plugin\n\n\n//# sourceURL=webpack://social-buddy/./src/css/style.scss?");

/***/ }),

/***/ "./src/index.ts":
/*!**********************!*\
  !*** ./src/index.ts ***!
  \**********************/
/***/ ((__unused_webpack_module, exports, __webpack_require__) => {

eval("\nObject.defineProperty(exports, \"__esModule\", ({ value: true }));\n//js\nvar main_1 = __webpack_require__(/*! ./js/main */ \"./src/js/main.ts\");\n//css\n__webpack_require__(/*! ./css/style.scss */ \"./src/css/style.scss\");\ndocument.addEventListener(\"DOMContentLoaded\", function () {\n    main_1.default.init();\n});\n\n\n//# sourceURL=webpack://social-buddy/./src/index.ts?");

/***/ }),

/***/ "./src/js/main.ts":
/*!************************!*\
  !*** ./src/js/main.ts ***!
  \************************/
/***/ ((__unused_webpack_module, exports) => {

eval("\nObject.defineProperty(exports, \"__esModule\", ({ value: true }));\nvar default_1 = /** @class */ (function () {\n    function default_1() {\n    }\n    default_1.init = function () {\n        button();\n        previews();\n    };\n    return default_1;\n}());\nexports[\"default\"] = default_1;\nfunction button() {\n    $('.social-buddy-publish').on('click', function () {\n        var key = $(this).attr('data-brandicon');\n        var val = $(this).attr('data-val');\n        if (confirm('Are you sure to post entry to ' + val + '?')) {\n            $('.action-input').val('convergine-socialbuddy/post/submit');\n            $('#sb_platform').val(key);\n            $('#main-form').trigger('submit');\n        }\n    });\n}\nfunction previews() {\n    $('.social-buddy-field-text textarea').on('input', function () {\n        var text = $(this).val();\n        if (text === '') {\n            text = \"&nbsp;\";\n        }\n        text = text.replace(/\\n/g, '<br>');\n        $('.social-buddy-preview .preview-text').html(text);\n    });\n    $('.social-buddy-field-board input[type=\"text\"]').on('input', function () {\n        var text = $(this).val();\n        if (text === '') {\n            text = \"&nbsp;\";\n        }\n        text = text.replace(/\\n/g, '<br>');\n        $('.social-buddy-preview .preview-title').html(text);\n    });\n    var $chips = $('.social-buddy-field-image .chips, .social-buddy-field-image .elements');\n    if ($chips.length) {\n        var observer = new MutationObserver(function (mutations) {\n            mutations.forEach(function (mutation) {\n                console.log('mutation.type', mutation.type);\n                var imageUrl = $chips.find('.chip, .element').attr('data-url');\n                var $previewImage = $chips.closest('.social-buddy').find('.preview-image');\n                console.log('imageUrl', imageUrl);\n                if (imageUrl === '') {\n                    $previewImage.html('');\n                }\n                else {\n                    $previewImage.html(\"<img src=\\\"\".concat(imageUrl, \"\\\" alt=\\\"\\\">\"));\n                }\n                return;\n            });\n        });\n        observer.observe($chips[0], {\n            attributes: true,\n            childList: true,\n            subtree: true,\n            characterData: true\n        });\n    }\n    // let $chip = $chips.find('.chip');\n    // if($chip.length) {\n    //     let observer = new MutationObserver(function(mutations) {\n    //         mutations.forEach(function(mutation) {\n    //             if(mutation.type === \"attributes\") {\n    //                 if(mutation.attributeName === \"data-url\") {\n    //                     let imageUrl = $chip.attr('data-url') as string;\n    //                     let $previewImage = $chips.closest('.social-buddy').find('.preview-image');\n    //                     if(imageUrl === '') {\n    //                         $previewImage.html('');\n    //                     } else {\n    //                         $previewImage.html(`<img src=\"${imageUrl}\" alt=\"Image\">`);\n    //                     }\n    //                 }\n    //                 return;\n    //             }\n    //         });\n    //     });\n    //     observer.observe($chip[0], {\n    //         attributes: true\n    //     });\n    // }\n}\n\n\n//# sourceURL=webpack://social-buddy/./src/js/main.ts?");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval devtool is used.
/******/ 	var __webpack_exports__ = __webpack_require__("./src/index.ts");
/******/ 	
/******/ })()
;