import type { Page } from '@playwright/test';

export async function contrastRatios(page: Page) {
  return page.evaluate(() => {
    const luminance = (color: number[]) => {
      const rgb = color.slice(0, 3).map((value) => {
        const component = value / 255;
        return component <= 0.04045 ? component / 12.92 : ((component + 0.055) / 1.055) ** 2.4;
      });
      return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
    };
    const ratio = (a: number[], b: number[]) => {
      const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
      return (values[0] + 0.05) / (values[1] + 0.05);
    };
    const parse = (value: string) => {
      const values = value.match(/[\d.]+/g)!.map(Number);
      return [values[0], values[1], values[2], values[3] ?? 1];
    };
    const over = (front: number[], back: number[]) => {
      const alpha = front[3] + back[3] * (1 - front[3]);
      return [...front.slice(0, 3).map((value, i) => alpha ? (value * front[3] + back[i] * back[3] * (1 - front[3])) / alpha : 0), alpha];
    };
    const pixel = (element: Element, foreground: boolean) => {
      let value = foreground ? parse(getComputedStyle(element).color) : [0, 0, 0, 0];
      // Include ancestor opacity so a transition cannot hide a contrast failure.
      for (let node: Element | null = element; node; node = node.parentElement) {
        const style = getComputedStyle(node);
        value = over(value, parse(style.backgroundColor));
        value[3] *= Number(style.opacity);
      }
      return over(value, [255, 255, 255, 1]);
    };
    const selectors = ['body', '.button', '.status', '.choice', '.field-error', '.field input', '.field select', '.muted'];
    return selectors.flatMap((selector) => [...document.querySelectorAll(selector)].filter(element => element.getClientRects().length).map((element) => {
      return { selector, ratio: ratio(pixel(element, true), pixel(element, false)) };
    }));
  });
}

export async function controlContrast(page: Page) {
  return page.evaluate(() => {
    const root = getComputedStyle(document.documentElement);
    const luminance = (hex: string) => {
      const value = hex.trim().replace('#', '');
      const expanded = value.length === 3 ? [...value].map(x => x + x).join('') : value;
      const rgb = expanded.match(/../g)!.map(x => parseInt(x, 16) / 255).map(x => x <= 0.04045 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4);
      return rgb[0] * .2126 + rgb[1] * .7152 + rgb[2] * .0722;
    };
    return ['--color-focus', '--color-control-border'].flatMap(foreground => ['--color-bg', '--color-surface', '--color-surface-subtle', '--color-selected-bg'].map(background => {
      const [a, b] = [foreground, background].map(token => luminance(root.getPropertyValue(token))).sort((a, b) => b - a);
      return { foreground, background, ratio: (a + .05) / (b + .05) };
    }));
  });
}
