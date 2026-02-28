foreach (KnowledgeBase::whereNull('embedding')->get() as $item) {
    $vector = app(EmbeddingService::class)
        ->generate($item->question . ' ' . $item->answer);

    if ($vector) {
        $item->update(['embedding' => json_encode($vector)]);
    }
}