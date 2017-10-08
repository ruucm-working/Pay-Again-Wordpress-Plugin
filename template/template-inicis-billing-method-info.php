<div class="payagain-cardpan">
	<ul class="paymentlist payagain-cardedit">
		<li>
			<div class="payagain-cardplate-wrap">
				<?php
					$gateway_pay_again = new WC_Gateway_INICIS_Pay_Again();
					$res = $gateway_pay_again->getPayAgainCustomer();
					if ($res->success):
				?>
				<div class="payagain-cardplate active">
					<div class="payagain-card-name">
						<?php echo $res->data->card_name; ?>
					</div>
					<div class="payagain-card-details">
						<div class="payagain-card-icon">
							<i class="icon ion-card"></i>
						</div>
						<div class="payagain-card-number">
							<div class="cardnum-dot">
								<?php
								if ($res->data->card_number) {
									echo '<div class="cardnum-dot">
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
							</div>
							<div class="cardnum-dot">
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
							</div>
							<div class="cardnum-dot">
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
								<i class="icon ion-record"></i>
							</div>';
									echo substr($res->data->card_number, -4);
								}
								else
									echo '<p class="hidden-cardnum-message">카드 번호는 보안상 이유로 가려져 있습니다</p>';
								?>
							</div>
						</div>
						<div class="payagain-added-date">추가된 날짜 : <?php echo gmdate("Y-m-d", $res->data->updated + 3600 * 9); ?></div>
						<i class="pay-again-delete-inicis-payment-button icon ion-close-circled"></i>
					</div>
				</div>
				<?php
					else:
						echo '(이니시스) 등록하신 카드 정보가 없습니다';
					endif;
				?>
			</div>
		</li>
	</ul>
</div>
