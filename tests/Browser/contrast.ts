import type { Page } from '@playwright/test';

export async function contrastRatios(page: Page) {
  return page.evaluate(() => {
    const luminance = (color: string) => {
      const rgb = color.match(/[\d.]+/g)!.slice(0, 3).map(Number).map((value) => {
        const component = value / 255;
        return component <= 0.04045 ? component / 12.92 : ((component + 0.055) / 1.055) ** 2.4;
      });
      return rgb[0] * 0.2126 + rgb[1] * 0.7152 + rgb[2] * 0.0722;
    };
    const ratio = (a: string, b: string) => {
      const values = [luminance(a), luminance(b)].sort((x, y) => y - x);
      return (values[0] + 0.05) / (values[1] + 0.05);
    };
    const selectors = ['body', '.button--primary', '.button--secondary', '.button--destructive', '.status--info', '.status--success', '.status--warning', '.status--error', '.choice', '.field-error'];
    return selectors.map((selector) => {
      const element = document.querySelector(selector)!;
      const style = getComputedStyle(element);
      let background = style.backgroundColor;
      let parent = element.parentElement;
      while (background === 'rgba(0, 0, 0, 0)' && parent) {
        background = getComputedStyle(parent).backgroundColor;
        parent = parent.parentElement;
      }
      return { selector, ratio: ratio(style.color, background) };
    });
  });
}
