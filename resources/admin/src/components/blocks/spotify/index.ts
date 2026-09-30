import { blockRegistry } from '../registry';
import { spotifyDefinition } from './definition';
import { SpotifyPreview } from './Preview';
import { SpotifyEditor } from './Editor';

blockRegistry.register(spotifyDefinition, SpotifyPreview, SpotifyEditor);
