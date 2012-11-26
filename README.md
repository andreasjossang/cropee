CropEE
======

Image Fieldtype for Expression Engine with built in crop and scaling methods.

Images are scaled and cropped when displayed, and there is a simple UI that lets user choose how the images will be displayed.


**Requires Assets Filetype.**


Tags
----

Single tag syntax

	{cropee href="<link>" class="<class>" alt="<text>" author_label="<photo>" width="<width>"}

Tag pair syntax

	{cropee}
		{image_org_filename}
		{image_zoom}
		{image_x}
		{image_y}		
	{/cropee}
	
	
Final note
----------

**This is the first testversion, more features will be added, tested and documented.**
