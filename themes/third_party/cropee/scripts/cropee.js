(function($){
	var $document = $(document);

cropee_field = function($field, id, settings){
	this.settings = settings;
	this.size = {};
	this.image = {};
	this.path = "";

	var field = this;

	var $field = $field.parent().parent();
	var $frame = $('div.frame', $field);
	var $input = $('input', $field);
	var $image = $('img', $field);
	var $select = $('button.select', $field);
	var $clear = $('button.clear', $field);
	var $info = $('button.info', $field);
	var $zoom = $('a.zoom', $field);	

	/**
	 * serialize
	 */
	var serialize = function() {
		var value = '';

		// has image
		if(field.path)
		{
			value = field.path;

			// not default scale and crop
			if(field.image.left != field.defaultImage.left ||
			   field.image.top != field.defaultImage.top ||
			   field.image.width != field.defaultImage.width || 
			   field.image.height != field.defaultImage.height)
			{
				var zoom = Math.round(field.image.width / field.size.width * 10000) / 100;
				var x = Math.round(-field.image.left / field.image.width * 10000) / 100;
				var y = Math.round(-field.image.top / field.image.height * 10000) / 100;

				value += "|" + zoom + "|" + x + "|" + y;
			}
		}

		$input.val(value);
	};

	/**
	 * unserialize
	 */
	var unserialize = function() {
		var value = $input.val();

		var zoom = null;
		var x = null;
		var y = null;

		if(value)
		{
			var values = value.split("|");
			field.path = values[0];
			if(values.length > 1)
				zoom = parseFloat(values[1]);
			if(values.length > 2)
				x = parseFloat(values[2]);
			if(values.length > 3)
				y = parseFloat(values[3]);
		}
		if(field.path)
		{
			var url = field.path.replace(/{filedir_(\d+)}/g, function(match, contents, offset, s) {
			        return field.settings.dir_prefs[contents].url;
			    }
			);
			setImage(field.path, url, zoom, x, y);
		}
		else
		{
			$select.show();
		}
	};

	/**
	 * setImage
	 */
	var setImage = function(path, url, zoom, x, y, update) {
		field.path = path;
		$image = null;

		$frame.css("visibility","visible").find(".image").empty().append($('<img />').attr("src", url))
			.blur(function(e) {
			serialize();
		}).imagesLoaded(function($img, $proper, $broken){
			$image = $img;

			field.size = { width : $img.width(), height : $img.height() };

			var width = field.size.width;
			var height = field.size.height;
			var top=0;
			var left=0;
			if(width <= 1 || height <= 1)
				return;

			// auto scale and crop
			if(width > height)
			{
				height = Math.round((height / width) * settings.width);
				width = settings.width;

				if(height < settings.height)
				{
					width = Math.round((width / height) * settings.height);
					height = settings.height;
				}
			}
			else
			{
				width = Math.round((width / height) * settings.height);
				height = settings.height;
				if(width < settings.width)
				{
					height = Math.round((height / width) * settings.width);
					width = settings.width;
				}
			}

			left = Math.round((settings.width - width) / 2);
			top = Math.round((settings.height - height) / 2);

			// store default values
			field.defaultImage = { left : left, top : top, width : width, height : height };

			// explicit given zoom and crop
			if(zoom)
			{
				height = Math.round(field.size.height * zoom / 100);
				width = Math.round(field.size.width * zoom / 100);

				if(x > 0)
					left = -Math.round(width * x / 100);
				if(y > 0)
					top = -Math.round(height * y / 100);
			}

			$img.css("width",width);
			$img.css("height",height);
			$img.css("top",top);
			$img.css("left",left);
			$img.css("visibility", "visible");

			field.image = { left : left, top : top, width : width, height : height };

			$select.hide();
			$info.show();
			$clear.show();

			if(update)
				serialize();
		});
	};

	/**
	 * move
	 */	
	var move = function(x, y) {
		var current_width = width = field.image.width;
		var current_height = height = field.image.height;
		var left = field.image.left;
		var top = field.image.top;

		
		if(x)
		{
			left += x;
			left = Math.min(left, 0);
			if((current_width - field.settings.width + left) < 0)
				left = -(current_width - field.settings.width);

			$image.css("left",left);
			field.image.left = left;
		}
		if(y)
		{
			top += y;
			top = Math.min(top, 0);
			if((current_height - field.settings.height + top) < 0)
				top = -(current_height - field.settings.height);

			$image.css("top",top);
			field.image.top = top;
		}		
	}

	/**
	 * zoom
	 */
	var zoom = function(scale) {
		var left = field.image.left;
		var top = field.image.top;
		var current_width = width = field.image.width;
		var current_height = height = field.image.height;

		if(field.settings.width > field.settings.height)
		{
			height += scale;
			if(height < field.settings.height)
				height = field.settings.height;

			width = Math.round((field.size.width / field.size.height) * height);

			if(width < field.settings.width)
			{
				height = Math.round((field.size.height / field.size.width) * field.settings.width);
				width = field.settings.width;
			}

//console.log(width + " x " + height + " [" + field.settings.width + " x " + field.settings.height + " ]");
//console.log(height + " != " + current_height + " , " + width + " != " + current_width);
		}
		else
		{
			width += scale;
			if(width < field.settings.width)
				width = field.settings.width;

			height = Math.round((field.size.height / field.size.width) * width);

			if(height < field.settings.height)
			{
				width = Math.round((field.size.width / field.size.height) * field.settings.height);
				height = field.settings.height;
			}
		}

		$image.css("width", width);
		$image.css("height", height);
		field.image.width = width;
		field.image.height = height;

		if(current_height != height)
		{
			top += Math.round((current_height - height) / 2);
			if(top > 0)
				top = 0;
			else if((height - field.settings.height) < -top)
				top = -(height - field.settings.height);

			$image.css("top",top);
			field.image.top = top;
		}

		if(current_width != width)
		{
			left += Math.round((current_width - width) / 2);
			if(left > 0)
				left = 0;
			else if((width - field.settings.width) < -left)
				left = -(width - field.settings.width);

			$image.css("left",left);
			field.image.left = left;
		}
	};

	unserialize();

	/*$frame.imagesLoaded(function($images, $proper, $broken){
		positionImage($images);
	});*/

	$select.click(function(e) {
		if(!field.sheet) {
			field.sheet = new Assets.Sheet({
				multiSelect: false,
				filedirs :    field.settings.file_dirs,
				onSelect :    function(files) {
					setImage(files[0].path,files[0].url, null, null, null, true);
				}
			});
		}
		field.sheet.show({});
	});

	$info.click(function(e) {
		$image.attr("data-path",field.path);
		new Assets.Properties($image);
	});

	$clear.click(function() {
		// reset image
		field.path='';
		$input.val("");
		$frame.css("visibility","hidden");
		$image.remove();
		$image = null;

		// toggle buttons
		$clear.hide();
		$info.hide();
		$select.show();
	});

	$zoom.click(function(e) {
		e.stopPropagation();
		e.preventDefault();

		var scale = e.metaKey ? 1 : 10;
		zoom($(this).hasClass("plus") ? scale : -scale);
	});

	$frame.mousedown(function(e) {
		e.preventDefault();
		e.stopPropagation();
		$(this).find(".image").focus();
		field.mouseDown = true;
		field.mousePosition = { x : e.pageX , y : e.pageY };
	});

	$frame.mousemove(function(e) {
		if(field.mouseDown)
		{
			move(e.pageX - field.mousePosition.x, e.pageY - field.mousePosition.y);
			field.mousePosition = { x : e.pageX , y : e.pageY };
		}
	});

	$(document).mouseup(function(e) {
		field.mouseDown = false;
	});

	$frame.keydown(function(e){
		switch(e.keyCode) {
			case 107:
				zoom(e.metaKey ? 1 : 10);
				break;
			case 109:
				zoom(e.metaKey ? -1 : -10);
				break;
			case 37:
				move(e.metaKey ? -1 : -10, 0);
				break;
			case 38:
				move(0, e.metaKey ? -1 : -10);
				break;
			case 39:
				move(e.metaKey ? 1 : 10, 0);
				break;
			case 40:
				move(0, e.metaKey ? 1 : 10);
				break;
			default: return;
		}
		e.preventDefault();
	});
};
})(jQuery);