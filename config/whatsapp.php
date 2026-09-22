<?php

return [
    'opt_out_keywords' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WHATSAPP_OPT_OUT_KEYWORDS', 'sair,parar,cancelar,descadastrar')),
    ))),
    'opt_out_confirmation' => 'Você foi descadastrado dos nossos envios de marketing e não receberá mais esse tipo de mensagem. Para voltar a receber, fale com a nossa equipe.',
];
