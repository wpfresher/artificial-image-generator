/**
 * Konva with only the parts the Studio draws with, to keep the bundle small.
 *
 * The core build doesn't fill in Konva.Filters, so the filters are exported here.
 */
import Konva from 'konva/lib/Core';
import 'konva/lib/shapes/Rect';
import 'konva/lib/shapes/Ellipse';
import 'konva/lib/shapes/Image';
import 'konva/lib/shapes/Text';
import 'konva/lib/shapes/Transformer';
import { Blur } from 'konva/lib/filters/Blur';
import { Brighten } from 'konva/lib/filters/Brighten';
import { Contrast } from 'konva/lib/filters/Contrast';
import { Grayscale } from 'konva/lib/filters/Grayscale';

export const Filters = { Blur, Brighten, Contrast, Grayscale };

export default Konva;
