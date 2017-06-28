<div class="cardpan">
	<!-- 주소입력폼 -->
	<div class="chkpayment section-bg-white">
		<!-- 결제수단목록 -->
		<ul class="paymentlist cardedit">
			<li>
				<div class="cardeditmsg">
					<h3 class="text-center">보유하신 카드 정보</h3>
				</div>
				<div class="cardplatewrap">
					<?php
						$gateway_pay_again = new WC_Gateway_Pay_Again();
						$res = $gateway_pay_again->getPayAgainCustomer();
						if ($res->success):
					?>
					<div class="cardplate active">
						<div class="cardname">[<?php echo $res->data->card_name; ?>]</div>
						<div class="cardinfo">
							<div class="cardnumber"><?php
								if ($res->data->card_number)
									echo '**** **** ***** ' . substr($res->data->card_number, -4);
								else
									echo '카드 번호는 보안상 이유로 가려져 있습니다';
								?></div>
							<div class="validdate">추가된 날짜 : <?php echo gmdate("Y-m-d", $res->data->updated + 3600 * 9); ?></div>
						</div>
						<a class="pay-again-delete-payment-button">삭제</a>
					</div>
					<?php
						else:
							echo '등록하신 카드 정보가 없습니다';
						endif;
					?>
				</div>
			</li>
		</ul>
		<!-- 결제수단목록 -->
	</div>
</div>
