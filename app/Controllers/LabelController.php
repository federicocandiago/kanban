<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Label;
use App\Services\Palette;

/** Each user's own labels (name optional, colour from the Trello palette). */
final class LabelController extends Controller
{
    public function store(Request $request): Response
    {
        $data = $this->validated($request);
        if (is_string($data)) {
            return $this->fail($data);
        }
        Label::create(['user_id' => auth()->id, ...$data]);

        return $this->ok(__('Label added.'), $this->panel());
    }

    public function update(Request $request, string $id): Response
    {
        $label = $this->mine((int) $id);
        $data = $this->validated($request);
        if (is_string($data)) {
            return $this->fail($data);
        }
        $label->update($data);

        return $this->ok(__('Label saved.'), $this->panel());
    }

    /** Also removes it from every card it was on. */
    public function destroy(Request $request, string $id): Response
    {
        $this->mine((int) $id)->delete();

        return $this->ok(__('Label deleted.'), $this->panel());
    }

    private function mine(int $id): Label
    {
        $label = Label::find($id);

        return $label && (int) $label->user_id === (int) auth()->id ? $label : abort(404);
    }

    /** @return array{name: string, color: string}|string the data, or the error message */
    private function validated(Request $request): array|string
    {
        $name = mb_substr((string) $request->input('name', ''), 0, 40);
        $color = (string) $request->input('color', '');

        return Palette::has($color) ? ['name' => $name, 'color' => $color] : __('Choose a colour.');
    }

    private function panel(): array
    {
        return ['html' => ['#labels-panel' => partial('labels', ['labels' => auth()->labels()])]];
    }
}
