function tSep(x){
	return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

(function($){
	if(typeof $ === "undefined"){
		return;
	}

	var productImageRoles = {
		1: "Portada web",
		2: "Portada delantera",
		3: "CD",
		4: "Portada posterior",
		5: "Portada interior"
	};

	$(function(){
		initializeProductImageManagers();
	});

	function initializeProductImageManagers(){
		$("form").each(function(){
			var $form = $(this);
			var action = $form.attr("action") || "";

			if(
				action.indexOf("postupload.php") === -1 &&
				action.indexOf("postupdate.php") === -1
			){
				return;
			}

			initializeProductImageManager($form);
		});
	}

	function initializeProductImageManager($form){
		if($form.data("product-image-manager-ready")){
			return;
		}

		$form.data(
			"product-image-manager-ready",
			true
		);

		var $legacyMainInput = $form.find(
			"input[name='newpicture']"
		).first();

		var $legacyMoreImagesInput = $form.find(
			"#moreimagesinput"
		).first();

		if(
			$legacyMainInput.length === 0 ||
			$legacyMoreImagesInput.length === 0
		){
			return;
		}

		var $manager = createProductImageManagerHtml();

		$legacyMainInput
			.prev("label")
			.before($manager);

		var $managerEnabledInput = $(
			"<input>",
			{
				type: "hidden",
				name: "product_image_manager",
				value: "0"
			}
		);

		var $stateInput = $(
			"<input>",
			{
				type: "hidden",
				name: "product_image_state",
				value: "{}"
			}
		);

		$form.append($managerEnabledInput);
		$form.append($stateInput);

		var productIdInput = $form.find(
			"input[name='id']"
		).first();

		var productId = productIdInput.length > 0
			? parseInt(productIdInput.val(), 10)
			: 0;

		if(productId > 0){
			setManagerStatus(
				$manager,
				"Cargando imágenes actuales..."
			);

			$.ajax({
				url: "productimages.php",
				method: "GET",
				dataType: "json",
				cache: false,
				data: {
					action: "get",
					id: productId
				}
			})
			.done(function(response){
				if(!response || response.ok !== true){
					restoreLegacyImageUi(
						$form,
						$manager,
						$managerEnabledInput
					);
					return;
				}

				activateProductImageManager(
					$form,
					$manager,
					$managerEnabledInput,
					$stateInput,
					response.slots || {}
				);
			})
			.fail(function(){
				restoreLegacyImageUi(
					$form,
					$manager,
					$managerEnabledInput
				);
			});
		}else{
			activateProductImageManager(
				$form,
				$manager,
				$managerEnabledInput,
				$stateInput,
				{}
			);
		}
	}

	function createProductImageManagerHtml(){
		var $manager = $(
			"<div>",
			{
				class: "product-image-manager"
			}
		);

		$manager.css({
			border: "1px solid #dddddd",
			padding: "18px",
			margin: "10px 0 25px 0",
			background: "#ffffff",
			boxSizing: "border-box"
		});

		var $title = $("<div>");
		$title.css({
			fontSize: "18px",
			fontWeight: "600",
			marginBottom: "5px",
			color: "#111111"
		});
		$title.text("Imágenes del CD");

		var $help = $("<div>");
		$help.css({
			fontSize: "12px",
			color: "#666666",
			marginBottom: "15px"
		});
		$help.text(
			"Agrega entre 2 y 5 imágenes. Cada tipo puede utilizarse una sola vez y el orden siempre será 1 → 5."
		);

		var $rows = $(
			"<div>",
			{
				class: "product-image-rows"
			}
		);

		var $addButton = $(
			"<button>",
			{
				type: "button",
				class: "product-image-add-button"
			}
		);

		$addButton.css({
			border: "1px solid #111111",
			background: "#ffffff",
			color: "#111111",
			padding: "9px 14px",
			cursor: "pointer",
			marginTop: "10px"
		});

		$addButton.html(
			"<i class='fa fa-plus'></i> Agregar otra imagen"
		);

		var $status = $(
			"<div>",
			{
				class: "product-image-manager-status"
			}
		);

		$status.css({
			fontSize: "12px",
			color: "#666666",
			marginTop: "10px"
		});

		$manager.append($title);
		$manager.append($help);
		$manager.append($rows);
		$manager.append($addButton);
		$manager.append($status);

		return $manager;
	}

	function activateProductImageManager(
		$form,
		$manager,
		$managerEnabledInput,
		$stateInput,
		slots
	){
		hideLegacyImageUi($form);

		$managerEnabledInput.val("1");

		var $rows = $manager.find(
			".product-image-rows"
		);

		$rows.empty();

		var currentImageCount = 0;

		for(var role = 1; role <= 5; role++){
			var path = getSlotValue(
				slots,
				role
			);

			if(path !== ""){
				addProductImageRow(
					$manager,
					role,
					path
				);
				currentImageCount++;
			}
		}

		if(currentImageCount === 0){
			addProductImageRow(
				$manager,
				1,
				""
			);

			addProductImageRow(
				$manager,
				2,
				""
			);
		}else if(currentImageCount === 1){
			addProductImageRow(
				$manager,
				findNextAvailableRole($manager),
				""
			);
		}

		setManagerStatus(
			$manager,
			""
		);

		updateProductImageRoleAvailability(
			$manager
		);

		$manager
			.find(".product-image-add-button")
			.off("click.productimages")
			.on("click.productimages", function(){
				var nextRole = findNextAvailableRole(
					$manager
				);

				if(nextRole === 0){
					return;
				}

				addProductImageRow(
					$manager,
					nextRole,
					""
				);

				updateProductImageRoleAvailability(
					$manager
				);
			});

		installProductImageSubmitValidation(
			$form,
			$manager,
			$stateInput
		);
	}

	function getSlotValue(slots, role){
		if(
			slots &&
			typeof slots[role] !== "undefined" &&
			slots[role] !== null
		){
			return String(slots[role]);
		}

		var stringRole = String(role);

		if(
			slots &&
			typeof slots[stringRole] !== "undefined" &&
			slots[stringRole] !== null
		){
			return String(slots[stringRole]);
		}

		return "";
	}

	function hideLegacyImageUi($form){
		var $mainInput = $form.find(
			"input[name='newpicture']"
		).first();

		var $mainLabel = $mainInput.prev(
			"label"
		);

		var $moreInput = $form.find(
			"#moreimagesinput"
		).first();

		var $moreVisual = $form.find(
			"#moreimagesvisual"
		).first();

		var $moreLabel = $moreVisual.prev(
			"label"
		);

		var $legacyAddButton = $moreInput.next(
			".buybutton"
		);

		$mainLabel.hide();
		$mainInput.hide().prop("disabled", true);

		$moreLabel.hide();
		$moreVisual.hide();
		$moreInput.hide();
		$legacyAddButton.hide();
	}

	function restoreLegacyImageUi(
		$form,
		$manager,
		$managerEnabledInput
	){
		$managerEnabledInput.val("0");

		var $mainInput = $form.find(
			"input[name='newpicture']"
		).first();

		var $mainLabel = $mainInput.prev(
			"label"
		);

		var $moreInput = $form.find(
			"#moreimagesinput"
		).first();

		var $moreVisual = $form.find(
			"#moreimagesvisual"
		).first();

		var $moreLabel = $moreVisual.prev(
			"label"
		);

		var $legacyAddButton = $moreInput.next(
			".buybutton"
		);

		$manager.remove();

		$mainLabel.show();
		$mainInput.show().prop("disabled", false);

		$moreLabel.show();
		$moreVisual.show();
		$legacyAddButton.show();
	}

	function addProductImageRow(
		$manager,
		role,
		existingPath
	){
		if($manager.find(".product-image-row").length >= 5){
			return;
		}

		var $row = $(
			"<div>",
			{
				class: "product-image-row"
			}
		);

		$row.css({
			display: "grid",
			gridTemplateColumns: "150px minmax(220px, 1fr) 230px 90px",
			gap: "10px",
			alignItems: "center",
			borderTop: "1px solid #eeeeee",
			padding: "12px 0"
		});

		$row.attr(
			"data-existing-path",
			existingPath || ""
		);

		var $preview = $(
			"<div>",
			{
				class: "product-image-preview"
			}
		);

		$preview.css({
			width: "140px",
			minHeight: "82px",
			border: "1px solid #e5e5e5",
			background: "#fafafa",
			display: "flex",
			alignItems: "center",
			justifyContent: "center",
			overflow: "hidden"
		});

		var $select = $(
			"<select>",
			{
				name: "product_image_roles[]",
				class: "product-image-role"
			}
		);

		$select.css({
			width: "100%",
			margin: "0",
			boxSizing: "border-box"
		});

		for(var roleNumber = 1; roleNumber <= 5; roleNumber++){
			$select.append(
				$("<option>", {
					value: roleNumber,
					text:
						roleNumber +
						" - " +
						productImageRoles[roleNumber]
				})
			);
		}

		$select.val(
			String(role)
		);

		var $fileInput = $(
			"<input>",
			{
				type: "file",
				name: "product_image_files[]",
				accept: "image/jpeg,image/png",
				class: "product-image-file"
			}
		);

		$fileInput.css({
			margin: "0",
			width: "100%",
			boxSizing: "border-box"
		});

		var $removeButton = $(
			"<button>",
			{
				type: "button",
				class: "product-image-remove"
			}
		);

		$removeButton.css({
			border: "1px solid #cccccc",
			background: "#ffffff",
			color: "#111111",
			padding: "8px",
			cursor: "pointer"
		});

		$removeButton.html(
			"<i class='fa fa-trash'></i> Quitar"
		);

		$row.append($preview);
		$row.append($select);
		$row.append($fileInput);
		$row.append($removeButton);

		$manager
			.find(".product-image-rows")
			.append($row);

		if(existingPath){
			renderExistingPreview(
				$preview,
				existingPath
			);
		}else{
			renderEmptyPreview(
				$preview
			);
		}

		$select.on(
			"change",
			function(){
				updateProductImageRoleAvailability(
					$manager
				);
			}
		);

		$fileInput.on(
			"change",
			function(){
				renderSelectedFilePreview(
					$preview,
					this,
					existingPath
				);
			}
		);

		$removeButton.on(
			"click",
			function(){
				$row.remove();

				if(
					$manager
						.find(".product-image-row")
						.length === 0
				){
					addProductImageRow(
						$manager,
						1,
						""
					);
				}

				updateProductImageRoleAvailability(
					$manager
				);
			}
		);
	}

	function renderExistingPreview(
		$preview,
		path
	){
		$preview.empty();

		var $img = $("<img>");

		$img.attr(
			"src",
			path
		);

		$img.css({
			maxWidth: "140px",
			maxHeight: "82px",
			display: "block"
		});

		$preview.append($img);
	}

	function renderEmptyPreview($preview){
		$preview.empty();

		var $text = $("<span>");
		$text.text("Sin imagen");
		$text.css({
			color: "#999999",
			fontSize: "11px"
		});

		$preview.append($text);
	}

	function renderSelectedFilePreview(
		$preview,
		input,
		existingPath
	){
		if(
			!input.files ||
			input.files.length === 0
		){
			if(existingPath){
				renderExistingPreview(
					$preview,
					existingPath
				);
			}else{
				renderEmptyPreview(
					$preview
				);
			}

			return;
		}

		var file = input.files[0];

		if(
			file.type !== "image/jpeg" &&
			file.type !== "image/png"
		){
			input.value = "";

			alert(
				"Solo se permiten imágenes JPG y PNG."
			);

			if(existingPath){
				renderExistingPreview(
					$preview,
					existingPath
				);
			}else{
				renderEmptyPreview(
					$preview
				);
			}

			return;
		}

		var objectUrl = URL.createObjectURL(
			file
		);

		$preview.empty();

		var $img = $("<img>");

		$img.attr(
			"src",
			objectUrl
		);

		$img.css({
			maxWidth: "140px",
			maxHeight: "82px",
			display: "block"
		});

		$preview.append($img);
	}

	function updateProductImageRoleAvailability(
		$manager
	){
		var usedRoles = [];

		$manager
			.find(".product-image-role")
			.each(function(){
				var value = String(
					$(this).val()
				);

				if(value !== ""){
					usedRoles.push(value);
				}
			});

		$manager
			.find(".product-image-role")
			.each(function(){
				var $select = $(this);
				var currentValue = String(
					$select.val()
				);

				$select
					.find("option")
					.each(function(){
						var $option = $(this);
						var optionValue = String(
							$option.val()
						);

						var alreadyUsed =
							usedRoles.indexOf(
								optionValue
							) !== -1;

						$option.prop(
							"disabled",
							alreadyUsed &&
							optionValue !== currentValue
						);
					});
			});

		var nextRole = findNextAvailableRole(
			$manager
		);

		$manager
			.find(".product-image-add-button")
			.prop(
				"disabled",
				nextRole === 0
			)
			.css(
				"opacity",
				nextRole === 0
					? "0.4"
					: "1"
			);
	}

	function findNextAvailableRole($manager){
		var usedRoles = {};

		$manager
			.find(".product-image-role")
			.each(function(){
				var role = parseInt(
					$(this).val(),
					10
				);

				if(role >= 1 && role <= 5){
					usedRoles[role] = true;
				}
			});

		for(var role = 1; role <= 5; role++){
			if(!usedRoles[role]){
				return role;
			}
		}

		return 0;
	}

	function installProductImageSubmitValidation(
		$form,
		$manager,
		$stateInput
	){
		var formElement = $form.get(0);

		if(
			!formElement ||
			$form.data("product-image-submit-validation")
		){
			return;
		}

		$form.data(
			"product-image-submit-validation",
			true
		);

		formElement.addEventListener(
			"submit",
			function(event){
				var validation = validateAndBuildImageState(
					$manager
				);

				if(!validation.ok){
					event.preventDefault();
					event.stopImmediatePropagation();

					alert(
						validation.message
					);

					return false;
				}

				$stateInput.val(
					JSON.stringify(
						validation.state
					)
				);

				return true;
			},
			true
		);
	}

	function validateAndBuildImageState(
		$manager
	){
		var state = {};
		var usedRoles = {};
		var configuredImages = 0;
		var hasWebCover = false;
		var errorMessage = "";

		$manager
			.find(".product-image-row")
			.each(function(){
				if(errorMessage !== ""){
					return;
				}

				var $row = $(this);

				var role = parseInt(
					$row
						.find(".product-image-role")
						.val(),
					10
				);

				if(role < 1 || role > 5){
					errorMessage =
						"Selecciona un tipo válido para cada imagen.";
					return;
				}

				if(usedRoles[role]){
					errorMessage =
						"Un tipo de imagen no puede repetirse.";
					return;
				}

				usedRoles[role] = true;

				var existingPath =
					$row.attr(
						"data-existing-path"
					) || "";

				var fileInput =
					$row
						.find(".product-image-file")
						.get(0);

				var hasNewFile =
					fileInput &&
					fileInput.files &&
					fileInput.files.length > 0;

				var hasImage =
					existingPath !== "" ||
					hasNewFile;

				if(hasImage){
					configuredImages++;

					if(role === 1){
						hasWebCover = true;
					}
				}

				if(existingPath !== ""){
					state[String(role)] =
						existingPath;
				}
			});

		if(errorMessage !== ""){
			return {
				ok: false,
				message: errorMessage,
				state: {}
			};
		}

		if(!hasWebCover){
			return {
				ok: false,
				message:
					"La Portada web (1) es obligatoria.",
				state: {}
			};
		}

		if(configuredImages < 2){
			return {
				ok: false,
				message:
					"Cada CD debe tener por lo menos 2 imágenes.",
				state: {}
			};
		}

		if(configuredImages > 5){
			return {
				ok: false,
				message:
					"Cada CD puede tener como máximo 5 imágenes.",
				state: {}
			};
		}

		return {
			ok: true,
			message: "",
			state: state
		};
	}

	function setManagerStatus(
		$manager,
		message
	){
		$manager
			.find(
				".product-image-manager-status"
			)
			.text(
				message || ""
			);
	}
})(window.jQuery);