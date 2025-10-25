<?php
	include 'includes/connection.php';
	require("language/language.php");

	$id=trim($_GET['id']);

?>

<style type="text/css">
	table td{
		vertical-align: top;
	}
</style>


<div class="row">
	<div class="col-md-6">
		<h4>Registration Details</h4>

		<div class="user_followings_block">
			<?php
				$sql="SELECT user.* FROM willdev_verify_user varify_u, willdev_users user WHERE varify_u.`user_id`=user.`id` AND varify_u.`id`='$id' AND user.`status`='1'";
				$res=mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
				$row=mysqli_fetch_assoc($res);
			?>
			<?php
				if(!file_exists('images/'.$row['user_image'])){
					?>
					<img src="images/<?=$row['user_image']?>" alt="user_photo" style="width: 150px;height:150px;">
					<?php
				}
				else{
					?>
					<img src="images/user-icons.jpg" alt="user_photo" style="width: 150px;height:150px;">
					<?php
				}
			?>
			<h3><?=$row['name']?></h3>
			<p><i class="fa fa-envelope-o"></i> <?=$row['email']?></p>
			<?php
				if(is_null($row['phone'])){
					?>
					<p><i class="fa fa-phone"></i> <?=$row['phone']?></p>
					<?php
				}
			?>

		</div>
	</div>
	<div class="col-md-6">
		<h4>Verification Details</h4>
		<div class="user_followings_block" style="text-align: justify;">
			<?php
				mysqli_free_result($res);

				$row=array();

				$sql="SELECT varify_u.* FROM willdev_verify_user varify_u, willdev_users user WHERE varify_u.`user_id`=user.`id` AND varify_u.`id`='$id'";
				$res=mysqli_query($mysqli, $sql) or die(mysqli_error($mysqli));
				$row=mysqli_fetch_assoc($res);
			?>
			<input type="hidden" name="action" value="verifyUser">
			<input type="hidden" name="verify_user_id" value="<?=$row['id']?>">
			<input type="hidden" name="user_id" value="<?=$row['user_id']?>">
			<strong style="font-weight: 500;margin-bottom: 10px">Full Name:</strong>
			<?=$row['full_name']?>
			<div style="width: 100%;border: 1px dashed #999;margin: 10px 0px"></div>
			<strong style="font-weight: 500">Document:&nbsp;&nbsp;</strong>
			<?php
				if(file_exists('images/documents/'.$row['document'])){
					?>
					<button type="button" class="btn btn-success btn_delete" style="margin-bottom:1px;" onclick="window.open('images/documents/<?=$row['document']?>','_blank')"><i class="fa fa-eye"></i> View</button>
					<?php
				}
				else{
					?>
					<p class="text-danger" style="float: right;">Not available</p>
					<?php
				}
			?>


			<div style="width: 100%;border: 1px dashed #999;margin: 10px 0px"></div>

			<strong style="font-weight: 500">Message:</strong>
			<hr style="margin-top: 5px;margin-bottom: 5px" />
			<?=$row['message']?>
		</div>
	</div>
	<div class="col-md-12 rejectReason" style="display: none;">
		<div class="form-group">
			<label class="form-lable">Reject Reason:</label>
			<textarea class="form-control" name="reject_reason" placeholder="Enter reject reason"></textarea>
		</div>
		<button type="submit" name="btn_process" value="processReject" class="btn btn-sm btn-success"><i class="fa fa-check-square-o"></i> Processed</button>
		<button name="btn_cancel" class="btn btn-sm btn-danger"><i class="fa fa-times"></i> Cancel</button>
	</div>
</div>


<script type="text/javascript">
	$("button[name='btn_process']").on("click",function(e){
		e.preventDefault();

		if($("textarea[name='reject_reason']").val()!=''){
			swal({
				title: "<?=$client_lang['are_you_sure_msg']?>",
				type: "warning",
				showCancelButton: true,
				confirmButtonClass: "btn-danger",
				cancelButtonClass: "btn-warning",
				confirmButtonText: "Yes!",
				cancelButtonText: "No",
				closeOnConfirm: false,
				closeOnCancel: false,
				showLoaderOnConfirm: true
			},
			function(isConfirm) {
				if (isConfirm)
				{
					$.ajax({
						type:'post',
						url:'processData.php',
						data : $("#verifyUserForm").serialize()+"&perform=reject",
						dataType:'json',
						success:function(res){
							if(res.status=='1'){
								location.reload();
							}
						}
					});
				}
				else {
					swal.close();
				}
			});
		}
		else{
			swal({title: 'Required!',text: '<?=$client_lang["reject_reason_err"]?>', type: 'warning'});
		}
	});

	$("button[name='btn_cancel']").on("click",function(e){

		e.preventDefault();

		$(".rejectReason").slideUp();
		$("#verifyUserForm button[name='btn_approve']").attr("disabled", false);
		$("#verifyUserForm button[name='btn_reject']").attr("disabled", false);
	});
</script>